<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Entity\ExchangeRate;
use App\Enum\ProviderEnum;
use App\Exception\LimitException;
use App\Message\FetchRateMessage;
use App\Repository\ExchangeRateRepository;
use App\Service\ProviderRegistry;
use App\Tests\WebTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Messenger\MessageBusInterface;

class AllProviderTest extends WebTestCase
{
    private ProviderRegistry $providerRegistry;
    private MessageBusInterface $bus;
    private ExchangeRateRepository $repo;

    protected function setUp(): void
    {
        self::bootKernel();
        // @phpstan-ignore assign.propertyType
        $this->providerRegistry = static::getContainer()->get(ProviderRegistry::class);
        // @phpstan-ignore assign.propertyType
        $this->bus = static::getContainer()->get(MessageBusInterface::class);
        // @phpstan-ignore assign.propertyType
        $this->repo = static::getContainer()->get(ExchangeRateRepository::class);

        $this->clearCache();
        $this->truncateEntities([ExchangeRate::class]);
    }

    /**
     * @return list<array{string}>
     */
    public static function providerKeys(): array
    {
        $specificProvider = getenv('TEST_PROVIDER');
        $data = [];
        foreach (ProviderEnum::cases() as $p) {
            if ($specificProvider && $specificProvider !== $p->value) {
                continue;
            }

            $data[] = [$p->value];
        }

        return $data;
    }

    #[DataProvider('providerKeys')]
    public function testRateRequestForAllProviders(string $providerKey): void
    {
        $this->ensureKernelShutdown();
        $client = static::createClient();

        $providerEnum = ProviderEnum::tryFrom($providerKey);
        if (!$providerEnum) {
            $this->markTestSkipped("Provider $providerKey not found");
        }

        try {
            $provider = $this->providerRegistry->get($providerEnum);
        } catch (\App\Exception\DisabledProviderException $e) {
            $this->markTestSkipped($e->getMessage());
        }

        // Задан день, с гарантированной правильной выдачей
        $date = (new \DateTimeImmutable('2026-02-04'));
        $currencies = $provider->getAvailableCurrencies();
        $this->assertGreaterThanOrEqual(2, count($currencies), 'Provider currencies should contain 2 or more: '.$providerKey);

        $currency = $currencies[0];
        $baseCurrency = $currencies[1];

        $url = sprintf(
            '/api/v1/rate?date=%s&currency=%s&baseCurrency=%s&provider=%s',
            $date->format('Y-m-d'),
            $currency,
            $baseCurrency,
            $providerKey,
        );

        $res = $this->jsonRequest($client, 'GET', $url, 202);
        $this->assertEmpty($res['rate']);
        $this->assertEmpty($res['rate_diff']);

        $this->fetchRateMessage(
            $date,
            $providerEnum,
        );
        $this->assertLog();

        $res = $this->jsonRequest($client, 'GET', $url, 202);

        $this->assertNotEmpty($res['rate']);
        $this->assertEmpty($res['diff']);

        $this->fetchRateMessage(
            $date->modify('-1 day'),
            $providerEnum,
        );
        $this->assertLog();

        // Повторный запрос выдает уже все данные (тк в тестах очередь выполняется как Sync)
        $res = $this->jsonRequest($client, 'GET', $url);
        $this->assertNotEmpty($res['rate']);
        $this->assertNotEmpty($res['diff']);

        // Simple work day
        $this->fetchRateMessage(
            new \DateTimeImmutable('2025-12-03'),
            $providerEnum,
        );
        $this->assertLog();
        // ///////////////////////////////////////////////////////////
        // Задан выходной день
        $date = (new \DateTimeImmutable('2026-01-01'));

        $this->fetchRateMessage(
            $date,
            $providerEnum,
        );

        $rates = $this->repo->findTwoLastRates($provider->getId(), $currency, $provider->getBaseCurrency(), $date);

        // Если нет курсов за эту конкретную дату, то в логах должна быть запись
        if (!$rates || $rates[0]->getDate()->format('Y-m-d') !== $date->format('Y-m-d')) {
            $this->assertLog([
                ['level' => 'info', 'message' => '/No data for '.$date->format('Y-m-d').'/'],
            ]);
        }
    }

    protected function fetchRateMessage(\DateTimeImmutable $date, ProviderEnum $providerEnum): void
    {
        try {
            $this->bus->dispatch(new FetchRateMessage(
                $date,
                $providerEnum,
            ));
        } catch (LimitException $e) {
            $this->markTestSkipped("Provider {$providerEnum->value} has rate limit");
        }
    }
}

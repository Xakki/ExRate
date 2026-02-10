<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Entity\ExchangeRate;
use App\Enum\ProviderEnum;
use App\Message\FetchRateMessage;
use App\Service\ProviderRegistry;
use App\Tests\WebTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Messenger\MessageBusInterface;

class AllProviderTest extends WebTestCase
{
    private ProviderRegistry $providerRegistry;
    private MessageBusInterface $bus;

    protected function setUp(): void
    {
        self::bootKernel();
        // @phpstan-ignore assign.propertyType
        $this->providerRegistry = static::getContainer()->get(ProviderRegistry::class);
        // @phpstan-ignore assign.propertyType
        $this->bus = static::getContainer()->get(MessageBusInterface::class);

        $this->clearCache();
        $this->truncateEntities([ExchangeRate::class]);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function providerKeys(): array
    {
        $specificProvider = getenv('TEST_PROVIDER');
        $data = [];
        foreach (ProviderEnum::cases() as $p) {
            if ($specificProvider && $specificProvider !== $p->value) {
                continue;
            }

            $data[$p->value] = [$p->value];
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

        $date = (new \DateTimeImmutable('2026-02-12'));
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

        $this->bus->dispatch(new FetchRateMessage(
            $date,
            $providerEnum,
        ));
        $this->assertLog();

        $res = $this->jsonRequest($client, 'GET', $url, 202);
        $this->assertNotEmpty($res['rate']);
        $this->assertEmpty($res['diff']);

        $this->bus->dispatch(new FetchRateMessage(
            $date->modify('-1 day'),
            $providerEnum,
        ));
        $this->assertLog();

        // Повторный запрос выдает уже все данные (тк в тестах очередь выполняется как Sync)
        $res = $this->jsonRequest($client, 'GET', $url);
        $this->assertNotEmpty($res['rate']);
        $this->assertNotEmpty($res['diff']);
    }
}

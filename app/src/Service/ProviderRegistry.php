<?php

declare(strict_types=1);

namespace App\Service;

use App\Contract\ProviderInterface;
use App\DTO\Provider;
use App\Enum\ProviderEnum;
use App\Exception\DisabledProviderException;
use App\Repository\ExchangeRateRepository;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireLocator;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

readonly class ProviderRegistry
{
    public const string CACHE_KEY = 'providers';

    public function __construct(
        #[AutowireLocator('app.provider', indexAttribute: 'key')]
        private ContainerInterface $locator,
        private CacheInterface $fastCache,
        private LoggerInterface $logger,
        private ExchangeRateRepository $exchangeRateRepository,
    ) {
    }

    public function get(ProviderEnum $provider): ProviderInterface
    {
        $serviceName = 'provider.'.$provider->value;

        if (!$this->locator->has($serviceName)) {
            throw new DisabledProviderException(sprintf('Rate provider %s not found', $provider->value));
        }

        /** @var ProviderInterface $service */
        $service = $this->locator->get($serviceName);

        if (!$service->isActive()) {
            throw new DisabledProviderException(sprintf('Rate provider %s is inactive', $provider->value));
        }

        return $service;
    }

    /**
     * @return Provider[]
     */
    public function getAll(bool $force = false): array
    {
        if ($force) {
            $this->fastCache->delete(self::CACHE_KEY);
        }

        return $this->fastCache->get(self::CACHE_KEY, function (ItemInterface $item) {
            $item->expiresAfter(3600);
            $providers = [];
            foreach (ProviderEnum::cases() as $providerEnum) {
                try {
                    $provider = $this->get($providerEnum);
                } catch (DisabledProviderException $e) {
                    $this->logger->info($e->getMessage(), ['provider' => $providerEnum->value]);
                    continue;
                }
                $providers[] = new Provider(
                    $provider->getEnum()->value,
                    $provider->getHomePage(),
                    $provider->getDescription(),
                    $provider->getBaseCurrency(),
                    $provider->getAvailableCurrencies(),
                    $this->exchangeRateRepository->getMinDate($provider->getId())?->format('Y-m-d')
                );
            }

            return $providers;
        });
    }
}

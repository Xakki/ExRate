<?php

declare(strict_types=1);

namespace App\Service;

use App\Contract\ProviderRateInterface;
use App\Contract\RateRepositoryInterface;
use App\Enum\ProviderEnum;
use App\Exception\DisabledProviderException;
use App\Response\ProviderResponse;
use App\Util\Date;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireLocator;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

readonly class ProviderRegistry
{
    public const string CACHE_KEY = 'providers';
    private const string ID_MAP_CACHE_KEY = 'providers_id_map';

    public function __construct(
        #[AutowireLocator('app.provider', indexAttribute: 'key')]
        private ContainerInterface $locator,
        private CacheInterface $fastCache,
        private LoggerInterface $logger,
        private RateRepositoryInterface $exchangeRateRepository,
    ) {
    }

    public function get(ProviderEnum $provider): ProviderRateInterface
    {
        $serviceName = 'provider.'.$provider->value;

        if (!$this->locator->has($serviceName)) {
            throw new DisabledProviderException(sprintf('Rate provider %s not found', $provider->value));
        }

        /** @var ProviderRateInterface $service */
        $service = $this->locator->get($serviceName);

        if (!$service->isActive()) {
            throw new DisabledProviderException(sprintf('Rate provider %s is inactive', $provider->value));
        }

        return $service;
    }

    public function getById(int $id): ProviderRateInterface
    {
        $map = $this->fastCache->get(self::ID_MAP_CACHE_KEY, function (ItemInterface $item): array {
            $item->expiresAfter(3600);
            $map = [];
            foreach (ProviderEnum::cases() as $providerEnum) {
                try {
                    $provider = $this->get($providerEnum);
                } catch (DisabledProviderException) {
                    continue;
                }
                $map[$provider->getId()] = $providerEnum->value;
            }

            return $map;
        });

        if (!isset($map[$id])) {
            throw new \InvalidArgumentException(sprintf('Provider with ID %d not found', $id));
        }

        return $this->get(ProviderEnum::from($map[$id]));
    }

    /**
     * @return ProviderResponse[]
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
                $providers[] = new ProviderResponse(
                    $provider->getEnum()->value,
                    $provider->getHomePage(),
                    $provider->getDescription(),
                    $provider->getBaseCurrency(),
                    $provider->getAvailableCurrencies(),
                    $this->exchangeRateRepository->getMinDate($provider)?->format(Date::FORMAT)
                );
            }

            return $providers;
        });
    }
}

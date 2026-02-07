<?php

declare(strict_types=1);

namespace App\Service;

use App\Contract\CacheManagerInterface;
use App\Entity\ExchangeRate;
use App\Enum\RateSource;
use App\Message\FetchRateMessage;
use App\Repository\ExchangeRateRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

readonly class ExchangeRateImporter
{
    private const string CACHE_KEY_CORRECTED_DAY = 'corrected_day_%s_%s';

    public function __construct(
        private RateSourceRegistry $rateSources,
        private ExchangeRateRepository $repository,
        private EntityManagerInterface $em,
        private CacheManagerInterface $cache,
        private MessageBusInterface $bus,
        private LoggerInterface $logger,
        private ExchangeRateProvider $provider,
    ) {
    }

    public function fetchAndSaveRates(\DateTimeImmutable $date, RateSource $rateSource): void
    {
        $source = $this->rateSources->get($rateSource);
        $correctedDate = $this->provider->getCorrectedDay($rateSource, $date);

        if ($this->repository->existRates($correctedDate, $source->getBaseCurrency(), $source->getId())) {
            $this->logger->info('Already exist rate for date '.$correctedDate->format('Y-m-d'));

            return;
        }

        $ratesResult = $source->getRates($correctedDate);
        $this->logger->info('Get rate for : '.$ratesResult->date->format('Y-m-d'));
        $this->saveCorrectDays($date, $ratesResult->date, $rateSource);

        $this->saveRates($ratesResult->date, $ratesResult->rates, $source->getId(), $source->getBaseCurrency());
    }

    protected function saveCorrectDays(\DateTimeImmutable $expectDate, \DateTimeImmutable $actualDate, RateSource $rateSource): void
    {
        if ($actualDate == $expectDate) {
            return;
        }

        // Если даты не совпали, грузим предыдущий день еще (для diff)
        $this->bus->dispatch(new FetchRateMessage(
            $actualDate->modify('-1 day'),
            $rateSource,
        ));

        $period = new \DatePeriod($actualDate, new \DateInterval('P1D'), $expectDate);

        foreach ($period as $day) {
            $targetDay = $day->modify('+1 day');
            $this->logger->info('Add correct date '.$targetDay->format('Y-m-d').' to '.$actualDate->format('Y-m-d'));
            $this->cache->set(
                sprintf(self::CACHE_KEY_CORRECTED_DAY, $rateSource->value, $targetDay->format('Y-m-d')),
                $actualDate->format('Y-m-d')
            );
        }
    }

    /**
     * @param array<string, string> $rates
     */
    private function saveRates(\DateTimeImmutable $date, array $rates, int $sourceId, string $baseCurrency): void
    {
        try {
            foreach ($rates as $code => $rateValue) {
                $rateEntity = new ExchangeRate();
                $rateEntity->setDate($date);
                $rateEntity->setCurrency($code);
                $rateEntity->setBaseCurrency($baseCurrency);
                $rateEntity->setRate($rateValue);
                $rateEntity->setSourceId($sourceId);
                $this->em->persist($rateEntity);
            }

            $this->em->flush();
        } catch (UniqueConstraintViolationException) {
            // Concurrency handling
            $this->em->clear(); // Detach all to avoid issues
        }
    }
}

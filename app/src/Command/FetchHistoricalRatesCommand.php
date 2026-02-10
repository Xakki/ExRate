<?php

declare(strict_types=1);

namespace App\Command;

use App\Contract\Cache\RateLimitCacheInterface;
use App\Contract\ProviderInterface;
use App\Enum\ProviderEnum;
use App\Exception\DisabledProviderException;
use App\Message\FetchRateMessage;
use App\Service\ProviderRegistry;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsCommand(
    name: 'app:fetch-history',
    description: 'Fetches historical exchange rates for the last N `days`. Optional for specific `provider`',
)]
class FetchHistoricalRatesCommand extends Command
{
    public function __construct(
        private readonly MessageBusInterface $bus,
        private readonly ProviderRegistry $providerRegistry,
        private readonly RateLimitCacheInterface $rateLimitCache,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('days', null, InputOption::VALUE_OPTIONAL, 'Number of days to fetch', 180)
            ->addOption('provider', null, InputOption::VALUE_OPTIONAL, 'Provider')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $providersEnum = [];
        $days = (int) $input->getOption('days');
        $provider = $input->getOption('provider');

        $output->writeln("Dispatching jobs to fetch rates for the last $days days for ".($provider ?: 'All'));

        if ($provider) {
            $providersEnum[] = ProviderEnum::from($provider);
        } else {
            $providersEnum = ProviderEnum::cases();
        }

        /** @var ProviderInterface[] $allowProviders */
        $allowProviders = [];
        foreach ($providersEnum as $providerEnum) {
            try {
                $allowProviders[] = $this->providerRegistry->get($providerEnum);
            } catch (DisabledProviderException) {
                // Skipp
            }
        }

        $today = new \DateTimeImmutable();

        foreach ($allowProviders as $provider) {
            $message = new FetchRateMessage($today, $provider->getEnum(), $days);
            $this->bus->dispatch($message, $message->getStamps(cache: $this->rateLimitCache, provider: $provider));
        }

        $output->writeln('Jobs dispatched successfully.');

        return Command::SUCCESS;
    }
}

<?php

namespace App\Command;

use App\Enum\RateSource;
use App\Message\FetchRateMessage;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsCommand(
    name: 'app:fetch-history',
    description: 'Fetches historical exchange rates for the last N days.',
)]
class FetchHistoricalRatesCommand extends Command
{
    public function __construct(private MessageBusInterface $bus)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('days', null, InputOption::VALUE_OPTIONAL, 'Number of days to fetch', 180)
            ->addOption('source', null, InputOption::VALUE_OPTIONAL, 'Source', RateSource::CBR->value)
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $days = (int) $input->getOption('days');
        $source = $input->getOption('source');

        $output->writeln("Dispatching jobs to fetch rates for the last $days days for $source");

        $today = new \DateTimeImmutable();

        for ($i = 0; $i < $days; ++$i) {
            $date = $today->modify("-$i days");
            $this->bus->dispatch(new FetchRateMessage(
                $date,
                RateSource::from($source)
            ));
        }

        $output->writeln('Jobs dispatched successfully.');

        return Command::SUCCESS;
    }
}

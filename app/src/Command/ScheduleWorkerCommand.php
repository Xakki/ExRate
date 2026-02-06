<?php

namespace App\Command;

use App\Message\FetchRateMessage;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsCommand(
    name: 'app:schedule-worker',
    description: 'Runs scheduled tasks (daily rate fetch).',
)]
class ScheduleWorkerCommand extends Command
{
    public function __construct(private MessageBusInterface $bus)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('Starting schedule worker...');

        $now = new \DateTimeImmutable();
        $nextRun = $now->setTime(12, 0, 0);

        if ($now >= $nextRun) {
            $nextRun = $nextRun->modify('+1 day');
        }

        $sleepSeconds = $nextRun->getTimestamp() - $now->getTimestamp();

        $output->writeln("Sleeping for {$sleepSeconds} seconds until ".$nextRun->format('Y-m-d H:i:s'));

        // Sleep until the next run
        sleep($sleepSeconds);

        // Double check time or just run (sleep might be interrupted or drift slightly, but usually fine)
        $output->writeln('Executing daily rate fetch...');

        try {
            $this->bus->dispatch(new FetchRateMessage(
                (new \DateTimeImmutable())->format('Y-m-d'),
                'USD',
                'RUB'
            ));
            $output->writeln('FetchRateMessage dispatched.');
        } catch (\Throwable $e) {
            $output->writeln('Error dispatching message: '.$e->getMessage());
        }

        // @phpstan-ignore-next-line
        return Command::SUCCESS;
    }
}

<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\ProviderRegistry;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:warmup-providers-cache',
    description: 'Warms up the providers cache.',
)]
class WarmupProvidersCacheCommand extends Command
{
    public function __construct(
        private readonly ProviderRegistry $providerRegistry,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('Warming up providers cache...');

        $this->providerRegistry->getAll(true);

        $output->writeln('Providers cache warmed up.');

        return Command::SUCCESS;
    }
}

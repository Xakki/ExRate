<?php

declare(strict_types=1);

namespace App\Command;

use App\Contract\ProviderInterface;
use App\Exception\DisabledProviderException;
use App\Service\ProviderRegistry;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Contracts\Cache\CacheInterface;

#[AsCommand(
    name: 'app:sync-provider-currencies',
    description: 'Syncs hardcoded available currencies in provider classes with actual data from API.',
)]
class SyncProviderCurrenciesCommand extends Command
{
    public function __construct(
        private readonly ProviderRegistry $providerRegistry,
        private readonly CacheInterface $fastCache,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Syncing Provider Currencies');

        $providers = \App\Enum\ProviderEnum::cases();
        $updatedCount = 0;

        foreach ($providers as $providerEnum) {
            try {
                $provider = $this->providerRegistry->get($providerEnum);
            } catch (DisabledProviderException) {
                $io->note(sprintf('Provider %s is disabled, skipping.', $providerEnum->value));
                continue;
            }

            $io->section(sprintf('Checking %s...', $providerEnum->value));

            try {
                $ratesResult = $provider->getRates(new \DateTimeImmutable());
                if (!count($ratesResult->rates)) {
                    $io->warning(sprintf('Provider %s return empty rates (Weekend?), skipping.', $providerEnum->value));
                    continue;
                }
                $newCurrencies = array_keys($ratesResult->rates);
                sort($newCurrencies);

                $currentCurrencies = $provider->getAvailableCurrencies();
                sort($currentCurrencies);

                if ($newCurrencies !== $currentCurrencies) {
                    $io->info(sprintf('Discrepancy found for %s. Updating file...', $providerEnum->value));
                    $io->writeln(sprintf('Old count: %d, New count: %d', count($currentCurrencies), count($newCurrencies)));

                    if ($this->updateProviderFile($provider, $newCurrencies)) {
                        ++$updatedCount;
                        $io->success(sprintf('Provider %s updated.', $providerEnum->value));
                    } else {
                        $io->error(sprintf('Failed to update provider %s file.', $providerEnum->value));
                    }
                } else {
                    $io->writeln('Currencies are up to date.');
                }
            } catch (\Throwable $e) {
                $io->error(sprintf('Failed to fetch rates for %s: %s', $providerEnum->value, $e->getMessage()));
            }
        }

        if ($updatedCount > 0) {
            $this->fastCache->delete(ProviderRegistry::CACHE_KEY);
            $io->success('Cache cleared. All providers synced.');
        } else {
            $io->writeln('No updates needed.');
        }

        return Command::SUCCESS;
    }

    /**
     * @param string[] $currencies
     */
    private function updateProviderFile(ProviderInterface $provider, array $currencies): bool
    {
        $reflection = new \ReflectionClass($provider);
        $fileName = $reflection->getFileName();

        if (!$fileName || !file_exists($fileName)) {
            return false;
        }

        $content = file_get_contents($fileName);
        if (false === $content) {
            return false;
        }

        // Format the array nicely
        $formattedArray = "['".implode("', '", $currencies)."']";

        // Match the method body. We assume the standard format implemented earlier.
        $pattern = '/public function getAvailableCurrencies\(\): array\s*\{[^}]+\}/s';
        $replacement = sprintf(
            "public function getAvailableCurrencies(): array\n    {\n        return %s;\n    }",
            $formattedArray
        );

        $newContent = preg_replace($pattern, $replacement, $content);

        if ($newContent && $newContent !== $content) {
            return (bool) file_put_contents($fileName, $newContent);
        }

        return false;
    }
}

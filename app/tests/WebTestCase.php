<?php

namespace App\Tests;

use Doctrine\ORM\EntityManager;
use Psr\Log\LogLevel;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\Console\Tester\CommandTester;

class WebTestCase extends \Symfony\Bundle\FrameworkBundle\Test\WebTestCase
{
    private const array LOG_LEVELS = [
        LogLevel::DEBUG => 0,
        LogLevel::INFO => 1,
        LogLevel::NOTICE => 2,
        LogLevel::WARNING => 3,
        LogLevel::ERROR => 4,
        LogLevel::CRITICAL => 5,
        LogLevel::ALERT => 6,
        LogLevel::EMERGENCY => 7,
    ];

    protected function setUp(): void
    {
        parent::setUp();
    }

    /**
     * @param array<int, array{level: string, message: string}> $expectedLogs
     *
     * @return mixed[]
     */
    protected function jsonRequest(KernelBrowser $client, string $method, string $url, int $expectedCode = 200, array $expectedLogs = []): array
    {
        $logPath = $this->getLogPath();
        if (file_exists($logPath)) {
            \App\Util\File::renameWithPrefix($logPath, str_replace('', '_', microtime()).'_');
        }

        $client->jsonRequest($method, $url);
        $responseContent = (string) $client->getResponse()->getContent();
        $this->assertJson($responseContent);
        $this->assertResponseStatusCodeSame($expectedCode, 'Failed response ['.$url.']: '.$client->getResponse()->getContent());
        $resp = json_decode($responseContent, true);
        $this->assertNotEmpty($resp);

        $this->assertLog($expectedLogs);

        return $resp;
    }

    /**
     * @param array<int, array{level: string, message: string}> $expectedLogs
     */
    protected function assertLog(array $expectedLogs = []): void
    {
        $logPath = $this->getLogPath();
        if (!file_exists($logPath)) {
            $this->assertEmpty($expectedLogs, 'Expected logs but log file does not exist');

            return;
        }

        $content = file_get_contents($logPath);
        $lines = empty($content) ? [] : explode("\n", trim($content));

        $foundFlags = array_fill(0, count($expectedLogs), false);

        foreach ($lines as $line) {
            if (empty($line)) {
                continue;
            }

            $matchedAnyExpected = false;
            foreach ($expectedLogs as $i => $expected) {
                $levelPattern = '.'.strtoupper($expected['level']).':';
                if (str_contains($line, $levelPattern) && preg_match($expected['message'], $line)) {
                    $foundFlags[$i] = true;
                    $matchedAnyExpected = true;
                    break;
                }
            }

            if (!$matchedAnyExpected) {
                // If not matched any expected log, check if it is NOTICE or higher
                foreach (self::LOG_LEVELS as $l => $v) {
                    if ($v >= self::LOG_LEVELS[LogLevel::NOTICE] && str_contains($line, '.'.strtoupper($l).':')) {
                        $this->fail("Unexpected log with level $l or higher found: $line");
                    }
                }
            }
        }

        foreach ($foundFlags as $i => $found) {
            if (!$found) {
                $this->fail(sprintf(
                    'Expected log not found: level=%s, message=%s',
                    $expectedLogs[$i]['level'],
                    $expectedLogs[$i]['message']
                ));
            }
        }
    }

    protected function getLogPath(): string
    {
        if (null === self::$kernel) {
            self::bootKernel();
        }

        /** @var string $path */
        $path = self::$kernel->getContainer()->getParameter('monolog.handlers.file_log.path');

        return $path;
    }

    protected function getEntityManager(): EntityManager
    {
        // @phpstan-ignore method.notFound
        return self::$kernel->getContainer()
            ->get('doctrine')
            ->getManager();
    }

    /**
     * @param class-string[] $entities
     */
    protected function truncateEntities(array $entities): void
    {
        $connection = $this->getEntityManager()->getConnection();
        $databasePlatform = $connection->getDatabasePlatform();
        $connection->executeQuery('SET FOREIGN_KEY_CHECKS=0');
        foreach ($entities as $entity) {
            $query = $databasePlatform->getTruncateTableSQL(
                $this->getEntityManager()->getClassMetadata($entity)->getTableName()
            );
            $connection->executeQuery($query);
        }
        $connection->executeQuery('SET FOREIGN_KEY_CHECKS=1');
    }

    protected function clearCache(): void
    {
        self::bootKernel();
        $application = new Application(self::$kernel);

        $command = $application->find('cache:pool:clear');
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            '--all' => true,
        ]);
        // cache.global_clearer
    }
}

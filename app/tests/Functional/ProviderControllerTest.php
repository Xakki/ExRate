<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\ExchangeRate;
use App\Tests\WebTestCase;

class ProviderControllerTest extends WebTestCase
{
    public function testGetProviders(): void
    {
        $client = static::createClient();
        $this->truncateEntities([ExchangeRate::class]);
        $this->clearCache();
        $resp = $this->jsonRequest($client, 'GET', '/api/v1/providers');

        foreach ($resp as $provider) {
            $this->assertArrayHasKey('key', $provider);
            $this->assertArrayHasKey('home_page', $provider);
            $this->assertArrayHasKey('description', $provider);
            $this->assertArrayHasKey('currencies',
                $provider);
            $this->assertIsString($provider['key']);
            $this->assertIsString($provider['home_page']);
            $this->assertIsString($provider['description']);
            $this->assertIsArray($provider['currencies']);
        }
    }
}

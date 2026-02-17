<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Tests\WebTestCase;
use App\Util\CryptoCurrencies;
use App\Util\Currencies;

class CurrencyControllerTest extends WebTestCase
{
    public function testGetCurrencies(): void
    {
        $client = static::createClient();
        $data = $this->jsonRequest($client, 'GET', '/api/currencies');

        $this->assertNotEmpty($data);

        // Check first element structure
        $first = $data[0];
        $this->assertArrayHasKey('code', $first);
        $this->assertArrayHasKey('symbol', $first);
        $this->assertArrayHasKey('name', $first);
        $this->assertArrayHasKey('countries', $first);

        // Verify some known currency
        $found = false;
        foreach ($data as $currency) {
            if (Currencies::USD === $currency['code']) {
                $this->assertEquals('$', $currency['symbol']);
                $this->assertEquals('US Dollar', $currency['name']);
                $found = true;
                break;
            }
        }
        $this->assertTrue($found, 'USD not found in currencies list');
    }

    public function testGetCryptoCurrencies(): void
    {
        $client = static::createClient();
        $data = $this->jsonRequest($client, 'GET', '/api/crypto_currencies');

        $this->assertNotEmpty($data);

        // Check first element structure
        $first = $data[0];
        $this->assertArrayHasKey('code', $first);
        $this->assertArrayHasKey('name', $first);
        $this->assertArrayHasKey('icon', $first);

        // Verify some known crypto currency
        $found = false;
        foreach ($data as $crypto) {
            if (CryptoCurrencies::BTC === $crypto['code']) {
                $this->assertEquals('Bitcoin', $crypto['name']);
                $this->assertEquals('icons/crypto/btc.svg', $crypto['icon']);
                $found = true;
                break;
            }
        }
        $this->assertTrue($found, 'BTC not found in crypto currencies list');
    }
}

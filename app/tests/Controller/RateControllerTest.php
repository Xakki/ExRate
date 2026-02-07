<?php

namespace App\Tests\Controller;

use App\DTO\RateResponse;
use App\Service\ExchangeRateProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class RateControllerTest extends WebTestCase
{
    public function testGetRateValidationFail(): void
    {
        $client = static::createClient();

        $rateServiceMock = $this->createMock(ExchangeRateProvider::class);
        static::getContainer()->set(ExchangeRateProvider::class, $rateServiceMock);

        $client->request('GET', '/api/v1/rate');
        $this->assertResponseStatusCodeSame(400);
    }

    public function testGetRateWithValidParams(): void
    {
        $client = static::createClient();

        $rateServiceMock = $this->createMock(ExchangeRateProvider::class);
        $rateServiceMock->method('getRate')->willReturn(new RateResponse('100', '1', '2024-01-01', '2023-12-31', false));

        static::getContainer()->set(ExchangeRateProvider::class, $rateServiceMock);

        $client->request('GET', '/api/v1/rate', ['currency' => 'USD']);
        $this->assertResponseStatusCodeSame(200);
    }
}

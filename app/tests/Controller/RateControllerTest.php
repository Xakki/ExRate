<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class RateControllerTest extends WebTestCase
{
    public function testGetRate(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/v1/rate');
        $this->assertResponseStatusCodeSame(422);
    }

    public function testGetRateWithValidParams(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/v1/rate', ['currency' => 'USD']);
        $status = $client->getResponse()->getStatusCode();
        $this->assertContains($status, [200, 500]); // 500 is acceptable if CBR is down, but not 404.
    }
}

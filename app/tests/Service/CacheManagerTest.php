<?php

namespace App\Tests\Service;

use App\Service\CacheManager;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class CacheManagerTest extends KernelTestCase
{
    private CacheItemPoolInterface $cache;
    private CacheManager $cacheManager;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->cache = $this->getContainer()->get('cache.app');
        $this->cacheManager = new CacheManager($this->cache);
    }

    public function testSetAndGet(): void
    {
        $this->cacheManager->set('test_key', 'test_value', 60);
        $this->assertTrue($this->cacheManager->has('test_key'));
        $this->assertEquals('test_value', $this->cacheManager->get('test_key'));
    }

    public function testGetNonExistent(): void
    {
        $this->assertFalse($this->cacheManager->has('non_existent_key'));
        $this->assertNull($this->cacheManager->get('non_existent_key'));
        $this->assertEquals('default', $this->cacheManager->get('non_existent_key', 'default'));
    }
}

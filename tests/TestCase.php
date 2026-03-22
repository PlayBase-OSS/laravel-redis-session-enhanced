<?php

namespace PlayBaseOss\Tests\LaravelRedisSessionEnhanced;

use Orchestra\Testbench\TestCase as BaseTestCase;
use PlayBaseOss\LaravelRedisSessionEnhanced\RedisSessionEnhancedServiceProvider as ServiceProvider;

abstract class TestCase extends BaseTestCase
{
    /**
     * Get package providers.
     *
     * @param  \Illuminate\Foundation\Application $app
     *
     * @return array
     */
    protected function getPackageProviders($app): array
    {
        return [
            ServiceProvider::class,
        ];
    }
}

<?php

namespace Craftsys\LaravelRedisSessionEnhanced;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Session;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Cache\Repository;

class RedisSessionEnhancedServiceProvider extends ServiceProvider
{
    private const DRIVER_NAME = 'redis-session';
    private const CACHE_DRIVER = 'redis';
    
    public function boot(): void
    {
        Session::extend(
            self::DRIVER_NAME,
            fn(Application $app) => $this->createSessionHandler($app)
        );
    }

    protected function createSessionHandler(Application $app): RedisSessionEnhancerHandler
    {
        $config = $app['config'];
        $cacheStore = $this->createCacheStore($app);
        
        $handler = new RedisSessionEnhancerHandler(
            cache: $cacheStore,
            minutes: $config->get('session.lifetime'),
            container: $app,
        );

        $this->configureConnection($handler, $config->get('session.connection'));

        return $handler;
    }

    protected function createCacheStore(Application $app): Repository
    {
        return clone $app->make('cache')->store(self::CACHE_DRIVER);
    }

    protected function configureConnection(
        RedisSessionEnhancerHandler $handler,
        ?string $connection
    ): void {
        if ($connection !== null) {
            $handler
                ->getCache()
                ->getStore()
                ->setConnection($connection);
        }
    }
}
<?php

namespace PlayBaseOss\LaravelRedisSessionEnhanced;

use Illuminate\Cache\Repository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\ServiceProvider;
use PlayBaseOss\LaravelRedisSessionEnhanced\Session\RedisSessionEnhancerHandler;

class RedisSessionEnhancedServiceProvider extends ServiceProvider
{
    private const DRIVER_NAME = 'redis-session';
    private const CACHE_DRIVER = 'redis';
    
    public function boot(): void
    {
        // Laravel's Manager::extend() calls bindTo() on the callback, which rebinds $this
        // to the Manager when given a regular or arrow closure. Using a static closure with
        // an explicit reference to $this via use() prevents that rebinding, which would
        // otherwise cause infinite recursion when the driver is first resolved.
        $provider = $this;

        Session::extend(
            self::DRIVER_NAME,
            static function (Application $app) use ($provider) {
                return $provider->createSessionHandler($app);
            }
        );
    }

    public function createSessionHandler(Application $app): RedisSessionEnhancerHandler
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
        // Clone to avoid mutating the shared cache store instance when setting the connection.
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
<?php

namespace PlayBaseOss\Tests\LaravelRedisSessionEnhanced;

use Illuminate\Support\Facades\Session;
use PlayBaseOss\LaravelRedisSessionEnhanced\Session\RedisSessionEnhancerHandler;

class DriverSetupTest extends TestCase
{
    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('session.driver', 'redis-session');
        $app['config']->set('session.connection', 'default');
        $app['config']->set('database.redis.default', [
            'host'     => env('REDIS_HOST', '127.0.0.1'),
            'port'     => env('REDIS_PORT', 6379),
            'database' => 0,
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();

        if (! $this->isRedisAvailable()) {
            $this->markTestSkipped('Redis is not available on ' . env('REDIS_HOST', '127.0.0.1') . ':' . env('REDIS_PORT', 6379));
        }
    }

    public function test_service_provider_registers_correct_session_handler(): void
    {
        $this->assertInstanceOf(RedisSessionEnhancerHandler::class, Session::getHandler());
    }

    private function isRedisAvailable(): bool
    {
        try {
            $socket = @fsockopen(
                env('REDIS_HOST', '127.0.0.1'),
                (int) env('REDIS_PORT', 6379),
                $errno,
                $errstr,
                1,
            );

            if ($socket === false) {
                return false;
            }

            fclose($socket);
            return true;
        } catch (\Throwable) {
            return false;
        }
    }
}

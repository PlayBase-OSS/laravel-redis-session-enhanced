<?php

namespace PlayBaseOss\Tests\LaravelRedisSessionEnhanced;

use PlayBaseOss\LaravelRedisSessionEnhanced\Session\RedisSessionEnhancerHandler;
use Illuminate\Support\Facades\Session;

class DriverSetupTest extends TestCase
{
    /**
     * Define environment setup.
     *
     * @param  \Illuminate\Foundation\Application $app
     *
     * @return void
     */
    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('session.driver', 'redis-session');
        $app['config']->set('session.connection', 'session');
    }

    /**
     * Test that we have the correct handler from container binding.
     *
     * @return void
     */
    public function testClientResolutionFromContainer(): void
    {
        $this->assertInstanceOf(RedisSessionEnhancerHandler::class, Session::getHandler());
    }
}

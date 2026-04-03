<?php

namespace PlayBaseOss\Tests\LaravelRedisSessionEnhanced\Unit\Support;

use PlayBaseOss\LaravelRedisSessionEnhanced\Support\SessionDriver;
use PlayBaseOss\Tests\LaravelRedisSessionEnhanced\TestCase;

class SessionDriverTest extends TestCase
{
    // -------------------------------------------------------------------------
    // Enum values
    // -------------------------------------------------------------------------

    public function test_database_driver_has_correct_value(): void
    {
        $this->assertSame('database', SessionDriver::Database->value);
    }

    public function test_redis_driver_has_correct_value(): void
    {
        $this->assertSame('redis-session', SessionDriver::Redis->value);
    }

    // -------------------------------------------------------------------------
    // isValid()
    // -------------------------------------------------------------------------

    public function test_database_driver_is_valid(): void
    {
        $this->assertTrue(SessionDriver::Database->isValid());
    }

    public function test_redis_driver_is_valid(): void
    {
        $this->assertTrue(SessionDriver::Redis->isValid());
    }

    // -------------------------------------------------------------------------
    // tryFrom()
    // -------------------------------------------------------------------------

    public function test_try_from_resolves_database(): void
    {
        $this->assertSame(SessionDriver::Database, SessionDriver::tryFrom('database'));
    }

    public function test_try_from_resolves_redis_session(): void
    {
        $this->assertSame(SessionDriver::Redis, SessionDriver::tryFrom('redis-session'));
    }

    public function test_try_from_returns_null_for_unknown_driver(): void
    {
        $this->assertNull(SessionDriver::tryFrom('file'));
        $this->assertNull(SessionDriver::tryFrom('unknown'));
        $this->assertNull(SessionDriver::tryFrom(''));
    }

    // -------------------------------------------------------------------------
    // current() — requires Laravel config()
    // -------------------------------------------------------------------------

    public function test_current_returns_redis_driver_when_configured(): void
    {
        config(['session.driver' => 'redis-session']);

        $this->assertSame(SessionDriver::Redis, SessionDriver::current());
    }

    public function test_current_returns_database_driver_when_configured(): void
    {
        config(['session.driver' => 'database']);

        $this->assertSame(SessionDriver::Database, SessionDriver::current());
    }

    public function test_current_returns_null_for_unsupported_driver(): void
    {
        config(['session.driver' => 'file']);

        $this->assertNull(SessionDriver::current());
    }

    public function test_current_returns_null_when_driver_is_not_set(): void
    {
        config(['session.driver' => null]);

        $this->assertNull(SessionDriver::current());
    }
}

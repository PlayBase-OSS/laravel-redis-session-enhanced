<?php

namespace PlayBaseOss\Tests\LaravelRedisSessionEnhanced\Unit\Support;

use Exception;
use Illuminate\Support\Facades\Session;
use PlayBaseOss\LaravelRedisSessionEnhanced\Session\RedisSessionEnhancerHandler;
use PlayBaseOss\LaravelRedisSessionEnhanced\Session\SessionData;
use PlayBaseOss\LaravelRedisSessionEnhanced\Support\SessionHelper;
use PlayBaseOss\Tests\LaravelRedisSessionEnhanced\TestCase;

class SessionHelperTest extends TestCase
{
    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('session.lifetime', 120);
    }

    // -------------------------------------------------------------------------
    // isUsingValidDriver()
    // -------------------------------------------------------------------------

    public function test_is_using_valid_driver_returns_true_for_redis_session(): void
    {
        config(['session.driver' => 'redis-session']);

        $this->assertTrue(SessionHelper::isUsingValidDriver());
    }

    public function test_is_using_valid_driver_returns_true_for_database(): void
    {
        config(['session.driver' => 'database']);

        $this->assertTrue(SessionHelper::isUsingValidDriver());
    }

    public function test_is_using_valid_driver_returns_false_for_file_driver(): void
    {
        config(['session.driver' => 'file']);

        $this->assertFalse(SessionHelper::isUsingValidDriver());
    }

    public function test_is_using_valid_driver_returns_false_for_unknown_driver(): void
    {
        config(['session.driver' => 'cookie']);

        $this->assertFalse(SessionHelper::isUsingValidDriver());
    }

    // -------------------------------------------------------------------------
    // getTimestampOfLastActivityForActiveSession()
    // -------------------------------------------------------------------------

    public function test_get_timestamp_reflects_session_lifetime_config(): void
    {
        config(['session.lifetime' => 60]);

        $expected = now()->subMinutes(60)->getTimestamp();

        $this->assertEqualsWithDelta($expected, SessionHelper::getTimestampOfLastActivityForActiveSession(), 2);
    }

    // -------------------------------------------------------------------------
    // getForUser() — invalid driver
    // -------------------------------------------------------------------------

    public function test_get_for_user_returns_empty_collection_for_invalid_driver(): void
    {
        config(['session.driver' => 'file']);

        $this->assertCount(0, SessionHelper::getForUser(1));
    }

    // -------------------------------------------------------------------------
    // getAll() — exception path
    // -------------------------------------------------------------------------

    public function test_get_all_throws_for_unsupported_driver(): void
    {
        config(['session.driver' => 'array']);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('SessionHelper can only be used for database/redis drivers');

        SessionHelper::getAll();
    }

    // -------------------------------------------------------------------------
    // deleteAll() — exception path
    // -------------------------------------------------------------------------

    public function test_delete_all_throws_for_unsupported_driver(): void
    {
        config(['session.driver' => 'array']);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('SessionHelper can only be used for database/redis drivers');

        SessionHelper::deleteAll();
    }

    // -------------------------------------------------------------------------
    // getAll() / getForUser() — Redis driver (mocked handler)
    // -------------------------------------------------------------------------

    public function test_get_all_from_redis_returns_all_sessions(): void
    {
        config(['session.driver' => 'redis-session']);

        $sessions = collect([
            new SessionData('s1', 1, '127.0.0.1', 'Agent', time(), ''),
            new SessionData('s2', 2, '192.168.0.1', 'Chrome', time(), ''),
        ]);

        Session::shouldReceive('getHandler')
            ->andReturn($this->mockHandler(readAll: $sessions));

        $result = SessionHelper::getAll();

        $this->assertCount(2, $result);
    }

    public function test_get_all_from_redis_filters_by_user_id(): void
    {
        config(['session.driver' => 'redis-session']);

        $sessions = collect([
            new SessionData('s1', 1, '127.0.0.1', 'Agent', time(), ''),
            new SessionData('s2', 2, '192.168.0.1', 'Chrome', time(), ''),
            new SessionData('s3', 1, '10.0.0.1', 'Firefox', time(), ''),
        ]);

        Session::shouldReceive('getHandler')
            ->andReturn($this->mockHandler(readAll: $sessions));

        $result = SessionHelper::getAll(user_id: 1);

        $this->assertCount(2, $result);
        $this->assertTrue($result->every(fn($s) => $s->user_id === 1));
    }

    public function test_get_for_user_returns_only_matching_sessions(): void
    {
        config(['session.driver' => 'redis-session']);
        config(['session.lifetime' => 120]);

        $sessions = collect([
            new SessionData('s1', 1, '127.0.0.1', 'Agent', time(), ''),
            new SessionData('s2', 2, '192.168.0.1', 'Chrome', time(), ''),
            new SessionData('s3', 1, '10.0.0.1', 'Firefox', time(), ''),
        ]);

        Session::shouldReceive('getHandler')
            ->andReturn($this->mockHandler(readAll: $sessions));

        $result = SessionHelper::getForUser(1);

        $this->assertCount(2, $result);
        $this->assertTrue($result->every(fn($s) => $s->user_id === 1));
    }

    public function test_get_for_user_with_only_active_excludes_expired_sessions(): void
    {
        config(['session.driver' => 'redis-session']);
        config(['session.lifetime' => 120]);

        $sessions = collect([
            new SessionData('active', 1, '127.0.0.1', 'Agent', time(), ''),
            new SessionData('expired', 1, '192.168.0.1', 'Chrome', now()->subMinutes(200)->getTimestamp(), ''),
        ]);

        Session::shouldReceive('getHandler')
            ->andReturn($this->mockHandler(readAll: $sessions));

        $result = SessionHelper::getForUser(1, only_active: true);

        $this->assertCount(1, $result);
        $this->assertSame('active', $result->first()->id);
    }

    public function test_get_for_user_sorted_by_last_activity_descending(): void
    {
        config(['session.driver' => 'redis-session']);
        config(['session.lifetime' => 120]);

        $older = now()->subMinutes(30)->getTimestamp();
        $newer = now()->subMinutes(5)->getTimestamp();

        $sessions = collect([
            new SessionData('older', 1, '127.0.0.1', 'Agent', $older, ''),
            new SessionData('newer', 1, '192.168.0.1', 'Chrome', $newer, ''),
        ]);

        Session::shouldReceive('getHandler')
            ->andReturn($this->mockHandler(readAll: $sessions));

        $result = SessionHelper::getForUser(1);

        $this->assertSame('newer', $result->first()->id);
        $this->assertSame('older', $result->last()->id);
    }

    // -------------------------------------------------------------------------
    // deleteAll() — Redis driver
    // -------------------------------------------------------------------------

    public function test_delete_all_from_redis_calls_destroy_all(): void
    {
        config(['session.driver' => 'redis-session']);

        $handler = \Mockery::mock(RedisSessionEnhancerHandler::class);
        $handler->shouldReceive('destroyAll')->once()->andReturn(true);

        Session::shouldReceive('getHandler')->andReturn($handler);

        SessionHelper::deleteAll();
    }

    // -------------------------------------------------------------------------
    // deleteForUserExceptSession()
    // -------------------------------------------------------------------------

    public function test_delete_for_user_except_session_destroys_other_sessions(): void
    {
        config(['session.driver' => 'redis-session']);
        config(['session.lifetime' => 120]);

        $sessions = collect([
            new SessionData('keep', 1, '127.0.0.1', 'Agent', time(), ''),
            new SessionData('delete', 1, '192.168.0.1', 'Chrome', time(), ''),
        ]);

        $destroyed = [];
        $handler = \Mockery::mock(RedisSessionEnhancerHandler::class);
        $handler->shouldReceive('readAll')->andReturn($sessions);
        $handler->shouldReceive('destroy')->andReturnUsing(function (string $id) use (&$destroyed) {
            $destroyed[] = $id;
            return true;
        });

        Session::shouldReceive('getHandler')->andReturn($handler);

        SessionHelper::deleteForUserExceptSession(1, 'keep');

        $this->assertSame(['delete'], $destroyed);
    }

    public function test_delete_for_user_except_session_destroys_all_when_no_exceptions(): void
    {
        config(['session.driver' => 'redis-session']);
        config(['session.lifetime' => 120]);

        $sessions = collect([
            new SessionData('s1', 1, '127.0.0.1', 'Agent', time(), ''),
            new SessionData('s2', 1, '192.168.0.1', 'Chrome', time(), ''),
        ]);

        $destroyed = [];
        $handler = \Mockery::mock(RedisSessionEnhancerHandler::class);
        $handler->shouldReceive('readAll')->andReturn($sessions);
        $handler->shouldReceive('destroy')->andReturnUsing(function (string $id) use (&$destroyed) {
            $destroyed[] = $id;
            return true;
        });

        Session::shouldReceive('getHandler')->andReturn($handler);

        SessionHelper::deleteForUserExceptSession(1, []);

        $this->assertCount(2, $destroyed);
        $this->assertContains('s1', $destroyed);
        $this->assertContains('s2', $destroyed);
    }

    // -------------------------------------------------------------------------
    // Helper
    // -------------------------------------------------------------------------

    private function mockHandler(\Illuminate\Support\Collection $readAll): RedisSessionEnhancerHandler
    {
        $handler = \Mockery::mock(RedisSessionEnhancerHandler::class);
        $handler->shouldReceive('readAll')->andReturn($readAll);
        return $handler;
    }
}

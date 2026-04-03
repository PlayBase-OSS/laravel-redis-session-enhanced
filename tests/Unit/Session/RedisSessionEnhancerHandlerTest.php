<?php

namespace PlayBaseOss\Tests\LaravelRedisSessionEnhanced\Unit\Session;

use Illuminate\Cache\Repository;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Contracts\Cache\Store;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PlayBaseOss\LaravelRedisSessionEnhanced\Session\RedisSessionEnhancerHandler;
use PlayBaseOss\Tests\LaravelRedisSessionEnhanced\TestCase;
use ReflectionProperty;

// The shared $cache mock is used as a stub in most tests and as a mock-with-expectations
// in a subset of tests. PHPUnit 13 requires explicit opt-out when mixing both usages.
#[AllowMockObjectsWithoutExpectations]
class RedisSessionEnhancerHandlerTest extends TestCase
{
    private Repository&MockObject $cache;
    private RedisSessionEnhancerHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cache = $this->createMock(Repository::class);
        $this->handler = new RedisSessionEnhancerHandler($this->cache, 120);
    }

    // -------------------------------------------------------------------------
    // read()
    // -------------------------------------------------------------------------

    public function test_read_returns_empty_string_when_nothing_cached(): void
    {
        $this->cache->method('get')->willReturn('');

        $this->assertSame('', $this->handler->read('session-id'));
    }

    public function test_read_returns_decoded_payload_for_valid_session(): void
    {
        $this->cache->method('get')->willReturn($this->makeRaw('hello-world'));

        $this->assertSame('hello-world', $this->handler->read('session-id'));
    }

    public function test_read_returns_empty_string_for_invalid_json(): void
    {
        $this->cache->method('get')->willReturn('{not:valid:json}}}');

        $this->assertSame('', $this->handler->read('session-id'));
    }

    public function test_read_returns_empty_string_for_expired_session(): void
    {
        $this->cache->method('get')->willReturn(json_encode([
            'payload'       => base64_encode('data'),
            'last_activity' => now()->subMinutes(200)->getTimestamp(),
        ]));

        $this->assertSame('', $this->handler->read('session-id'));
    }

    public function test_read_returns_empty_string_when_payload_key_is_missing(): void
    {
        $this->cache->method('get')->willReturn(json_encode([
            'last_activity' => time(),
        ]));

        $this->assertSame('', $this->handler->read('session-id'));
    }

    public function test_read_sets_exists_true_for_valid_session(): void
    {
        $this->cache->method('get')->willReturn($this->makeRaw('data'));

        $this->handler->read('session-id');

        $this->assertTrue($this->getExists());
    }

    public function test_read_sets_exists_true_for_expired_session(): void
    {
        $this->cache->method('get')->willReturn(json_encode([
            'payload'       => base64_encode('data'),
            'last_activity' => now()->subMinutes(200)->getTimestamp(),
        ]));

        $this->handler->read('session-id');

        $this->assertTrue($this->getExists());
    }

    public function test_read_leaves_exists_false_for_empty_cache(): void
    {
        $this->cache->method('get')->willReturn('');

        $this->handler->read('session-id');

        $this->assertFalse($this->getExists());
    }

    public function test_read_leaves_exists_false_for_invalid_json(): void
    {
        $this->cache->method('get')->willReturn('not-json');

        $this->handler->read('session-id');

        $this->assertFalse($this->getExists());
    }

    public function test_read_not_expired_within_session_lifetime(): void
    {
        $this->cache->method('get')->willReturn(json_encode([
            'payload'       => base64_encode('data'),
            'last_activity' => now()->subMinutes(119)->getTimestamp(),
        ]));

        $this->assertSame('data', $this->handler->read('session-id'));
    }

    // -------------------------------------------------------------------------
    // write()
    // -------------------------------------------------------------------------

    public function test_write_returns_true(): void
    {
        $this->cache->method('get')->willReturn('');
        $this->cache->method('put')->willReturn(true);

        $this->assertTrue($this->handler->write('session-id', 'data'));
    }

    public function test_write_stores_base64_encoded_payload(): void
    {
        $this->cache->method('get')->willReturn('');
        $captured = null;
        $this->cache->method('put')->willReturnCallback(
            function ($key, $value) use (&$captured) {
                $captured = json_decode($value, true);
                return true;
            }
        );

        $this->handler->write('session-id', 'my-session-data');

        $this->assertNotNull($captured);
        $this->assertSame('my-session-data', base64_decode($captured['payload']));
    }

    public function test_write_stores_last_activity_timestamp(): void
    {
        $this->cache->method('get')->willReturn('');
        $before = time();
        $captured = null;
        $this->cache->method('put')->willReturnCallback(
            function ($key, $value) use (&$captured) {
                $captured = json_decode($value, true);
                return true;
            }
        );

        $this->handler->write('session-id', 'data');

        $this->assertArrayHasKey('last_activity', $captured);
        $this->assertGreaterThanOrEqual($before, $captured['last_activity']);
        $this->assertLessThanOrEqual(time(), $captured['last_activity']);
    }

    public function test_write_sets_exists_true(): void
    {
        $this->cache->method('get')->willReturn('');
        $this->cache->method('put')->willReturn(true);

        $this->handler->write('session-id', 'data');

        $this->assertTrue($this->getExists());
    }

    public function test_write_skips_read_when_exists_is_already_true(): void
    {
        $this->handler->setExists(true);
        $this->cache->expects($this->never())->method('get');
        $this->cache->method('put')->willReturn(true);

        $this->handler->write('session-id', 'data');
    }

    public function test_write_calls_read_when_exists_is_false(): void
    {
        $this->cache->expects($this->once())->method('get')->willReturn('');
        $this->cache->method('put')->willReturn(true);

        $this->handler->write('session-id', 'data');
    }

    public function test_write_without_container_omits_user_metadata(): void
    {
        $this->cache->method('get')->willReturn('');
        $captured = null;
        $this->cache->method('put')->willReturnCallback(
            function ($key, $value) use (&$captured) {
                $captured = json_decode($value, true);
                return true;
            }
        );

        $this->handler->write('session-id', 'data');

        $this->assertArrayNotHasKey('user_id', $captured);
        $this->assertArrayNotHasKey('ip_address', $captured);
        $this->assertArrayNotHasKey('user_agent', $captured);
    }

    public function test_write_includes_user_id_when_guard_is_bound(): void
    {
        $guard = $this->createMock(Guard::class);
        $guard->method('id')->willReturn(99);

        $handler = new RedisSessionEnhancerHandler(
            $this->cache, 120,
            $this->makeContainer([Guard::class], [Guard::class => $guard])
        );
        $handler->setExists(true);

        $captured = null;
        $this->cache->method('put')->willReturnCallback(
            function ($key, $value) use (&$captured) {
                $captured = json_decode($value, true);
                return true;
            }
        );

        $handler->write('session-id', 'data');

        $this->assertArrayHasKey('user_id', $captured);
        $this->assertSame(99, $captured['user_id']);
    }

    public function test_write_includes_ip_and_user_agent_when_request_is_bound(): void
    {
        // PHPUnit 13 cannot double classes with a method named "method" (e.g. Request),
        // so we use Mockery for the Request mock.
        $request = \Mockery::mock(Request::class);
        $request->shouldReceive('ip')->andReturn('10.0.0.1');
        $request->shouldReceive('header')->with('User-Agent')->andReturn('TestBrowser/2.0');

        $handler = new RedisSessionEnhancerHandler(
            $this->cache, 120,
            $this->makeContainer(['request'], ['request' => $request])
        );
        $handler->setExists(true);

        $captured = null;
        $this->cache->method('put')->willReturnCallback(
            function ($key, $value) use (&$captured) {
                $captured = json_decode($value, true);
                return true;
            }
        );

        $handler->write('session-id', 'data');

        $this->assertSame('10.0.0.1', $captured['ip_address']);
        $this->assertSame('TestBrowser/2.0', $captured['user_agent']);
    }

    public function test_write_truncates_user_agent_to_500_characters(): void
    {
        $request = \Mockery::mock(Request::class);
        $request->shouldReceive('ip')->andReturn('127.0.0.1');
        $request->shouldReceive('header')->with('User-Agent')->andReturn(str_repeat('A', 600));

        $handler = new RedisSessionEnhancerHandler(
            $this->cache, 120,
            $this->makeContainer(['request'], ['request' => $request])
        );
        $handler->setExists(true);

        $captured = null;
        $this->cache->method('put')->willReturnCallback(
            function ($key, $value) use (&$captured) {
                $captured = json_decode($value, true);
                return true;
            }
        );

        $handler->write('session-id', 'data');

        $this->assertSame(500, mb_strlen($captured['user_agent']));
    }

    // -------------------------------------------------------------------------
    // setExists() / setContainer()
    // -------------------------------------------------------------------------

    public function test_set_exists_updates_the_flag(): void
    {
        $this->handler->setExists(true);
        $this->assertTrue($this->getExists());

        $this->handler->setExists(false);
        $this->assertFalse($this->getExists());
    }

    public function test_set_exists_returns_self(): void
    {
        $this->assertSame($this->handler, $this->handler->setExists(true));
    }

    public function test_set_container_returns_self(): void
    {
        $app = $this->createMock(Application::class);
        $this->assertSame($this->handler, $this->handler->setContainer($app));
    }

    // -------------------------------------------------------------------------
    // destroyAll()
    // -------------------------------------------------------------------------

    public function test_destroy_all_flushes_the_cache_store(): void
    {
        $store = $this->createMock(Store::class);
        $store->expects($this->once())->method('flush')->willReturn(true);
        $this->cache->method('getStore')->willReturn($store);

        $this->assertTrue($this->handler->destroyAll());
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function getExists(): bool
    {
        return (new ReflectionProperty($this->handler, 'exists'))->getValue($this->handler);
    }

    /** Build a raw JSON session string as stored in Redis. */
    private function makeRaw(string $payload, ?int $lastActivity = null): string
    {
        return json_encode([
            'payload'       => base64_encode($payload),
            'last_activity' => $lastActivity ?? time(),
        ]);
    }

    /** Build a container mock that returns true for bound() calls matching $bound and routes make() via $resolve. */
    private function makeContainer(array $bound, array $resolve): Container
    {
        $container = $this->createMock(Container::class);
        $container->method('bound')->willReturnCallback(
            fn(string $abstract) => in_array($abstract, $bound, strict: true)
        );
        $container->method('make')->willReturnCallback(
            fn(string $abstract) => $resolve[$abstract] ?? null
        );
        return $container;
    }
}

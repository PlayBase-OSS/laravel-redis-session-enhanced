<?php

namespace PlayBaseOss\Tests\LaravelRedisSessionEnhanced\Unit\Session;

use Error;
use PHPUnit\Framework\TestCase;
use PlayBaseOss\LaravelRedisSessionEnhanced\Session\SessionData;

class SessionDataTest extends TestCase
{
    public function test_stores_all_fields(): void
    {
        $data = new SessionData(
            id: 'session-id-123',
            user_id: 42,
            ip_address: '127.0.0.1',
            user_agent: 'Mozilla/5.0',
            last_activity: 1700000000,
            payload: 'base64payload',
        );

        $this->assertSame('session-id-123', $data->id);
        $this->assertSame(42, $data->user_id);
        $this->assertSame('127.0.0.1', $data->ip_address);
        $this->assertSame('Mozilla/5.0', $data->user_agent);
        $this->assertSame(1700000000, $data->last_activity);
        $this->assertSame('base64payload', $data->payload);
    }

    public function test_accepts_null_user_id(): void
    {
        $data = new SessionData(
            id: 'session-id',
            user_id: null,
            ip_address: '',
            user_agent: '',
            last_activity: 0,
            payload: '',
        );

        $this->assertNull($data->user_id);
    }

    public function test_accepts_string_user_id(): void
    {
        $data = new SessionData(
            id: 'session-id',
            user_id: 'uuid-string',
            ip_address: '192.168.1.1',
            user_agent: 'Chrome',
            last_activity: 1700000000,
            payload: '',
        );

        $this->assertSame('uuid-string', $data->user_id);
    }

    public function test_properties_are_readonly(): void
    {
        $data = new SessionData(
            id: 'session-id',
            user_id: 1,
            ip_address: '127.0.0.1',
            user_agent: 'Agent',
            last_activity: 1000,
            payload: 'payload',
        );

        $this->expectException(Error::class);
        $data->id = 'other-id'; // @phpstan-ignore-line
    }
}

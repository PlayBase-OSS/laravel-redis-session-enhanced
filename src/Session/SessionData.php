<?php

namespace PlayBaseOss\LaravelRedisSessionEnhanced\Session;

readonly class SessionData
{
    public function __construct(
        public string $id,
        public mixed $user_id,
        public string $ip_address,
        public string $user_agent,
        public int $last_activity,
        public string $payload,
    ) {}
}

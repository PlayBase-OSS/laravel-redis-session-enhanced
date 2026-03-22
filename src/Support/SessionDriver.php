<?php

namespace PlayBaseOss\LaravelRedisSessionEnhanced\Support;

enum SessionDriver: string
{
    case Database = 'database';
    case Redis = 'redis-session';

    public static function current(): ?self
    {
        return self::tryFrom(config('session.driver'));
    }

    public function isValid(): bool
    {
        return match ($this) {
            self::Database, self::Redis => true,
        };
    }
}

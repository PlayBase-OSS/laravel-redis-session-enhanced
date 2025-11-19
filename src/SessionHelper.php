<?php

namespace Craftsys\LaravelRedisSessionEnhanced;

use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;

enum SessionDriver: string
{
    case Database = 'database';
    case Redis = 'redis-session';

    public static function current(): ?self
    {
        return self::tryFrom(config('session.driver')) ?? null;
    }

    public function isValid(): bool
    {
        return match ($this) {
            self::Database, self::Redis => true,
        };
    }
}

class SessionHelper
{
    /**
     * Get all sessions for the given user ID
     * 
     * @param int|string $user_id ID/Key of the user, must match the ID used in sessions
     * @param bool $only_active Get only active sessions
     */
    public static function getForUser(
        int|string $user_id,
        bool $only_active = false
    ): Collection {
        if (!self::isUsingValidDriver()) {
            return collect();
        }

        return self::getAll($user_id)
            ->when(
                $only_active,
                fn(Collection $sessions) => $sessions->where(
                    'last_activity',
                    '>=',
                    self::getTimestampOfLastActivityForActiveSession(),
                )
            )
            ->sortByDesc('last_activity')
            ->values();
    }

    /**
     * Delete a user's sessions except the given session IDs
     * 
     * @param int|string $user_id
     * @param string|array<string> $except_session_id Non-deletable session IDs, pass empty to delete all
     */
    public static function deleteForUserExceptSession(
        int|string $user_id,
        string|array $except_session_id = []
    ): void {
        $sessionIds = (array) $except_session_id;
        
        self::getForUser($user_id)
            ->when(
                $sessionIds !== [],
                fn(Collection $sessions) => $sessions->whereNotIn('id', $sessionIds)
            )
            ->each(fn(object $session) => Session::getHandler()->destroy($session->id));
    }

    /**
     * Get all sessions from the store
     * 
     * @param null|int|string $user_id Optionally get all stored sessions of a particular user
     */
    public static function getAll(null|int|string $user_id = null): Collection
    {
        return match (SessionDriver::current()) {
            SessionDriver::Database => self::getAllFromDatabase($user_id),
            SessionDriver::Redis => self::getAllFromRedis($user_id),
            null => throw new Exception(
                'SessionHelper can only be used for database/redis drivers'
            ),
        };
    }

    protected static function getAllFromDatabase(null|int|string $user_id): Collection
    {
        return DB::connection(config('session.connection'))
            ->table(config('session.table', 'sessions'))
            ->when(
                $user_id !== null,
                fn($query) => $query->where('user_id', $user_id)
            )
            ->get();
    }

    protected static function getAllFromRedis(null|int|string $user_id): Collection
    {
        /** @var RedisDatabaseLikeSessionHandler $handler */
        $handler = Session::getHandler();
        $sessions = $handler->readAll();

        return $user_id !== null
            ? $sessions->where('user_id', $user_id)
            : $sessions;
    }

    /**
     * Destroy all session data
     */
    public static function deleteAll(): void
    {
        match (SessionDriver::current()) {
            SessionDriver::Database => self::deleteAllFromDatabase(),
            SessionDriver::Redis => self::deleteAllFromRedis(),
            null => throw new Exception(
                'SessionHelper can only be used for database/redis drivers'
            ),
        };
    }

    protected static function deleteAllFromDatabase(): void
    {
        DB::connection(config('session.connection'))
            ->table(config('session.table', 'sessions'))
            ->truncate();
    }

    protected static function deleteAllFromRedis(): void
    {
        /** @var RedisDatabaseLikeSessionHandler $handler */
        $handler = Session::getHandler();
        $handler->destroyAll();
    }

    protected static function isUsingDatabaseDriver(): bool
    {
        return SessionDriver::current() === SessionDriver::Database;
    }

    protected static function isUsingRedisDatabaseDriver(): bool
    {
        return SessionDriver::current() === SessionDriver::Redis;
    }

    public static function isUsingValidDriver(): bool
    {
        return SessionDriver::current()?->isValid() ?? false;
    }

    /**
     * Get the timestamp of last activity which results in an active session
     */
    public static function getTimestampOfLastActivityForActiveSession(): int
    {
        return now()
            ->subMinutes(config('session.lifetime'))
            ->getTimestamp();
    }
}
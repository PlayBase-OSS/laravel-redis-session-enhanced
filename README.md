# Laravel Redis Session Enhanced

<p>
<a href="https://packagist.org/packages/playbase-oss/laravel-redis-session-enhanced"><img src="https://img.shields.io/packagist/dt/playbase-oss/laravel-redis-session-enhanced" alt="Total Downloads" /></a>
<a href="https://packagist.org/packages/playbase-oss/laravel-redis-session-enhanced"><img src="https://img.shields.io/packagist/v/playbase-oss/laravel-redis-session-enhanced?label=version" alt="Latest Stable Version" /></a>
<a href="https://packagist.org/packages/playbase-oss/laravel-redis-session-enhanced"><img src="https://img.shields.io/packagist/l/playbase-oss/laravel-redis-session-enhanced" alt="License" /></a>
<a href="https://github.com/PlayBase-OSS/laravel-redis-session-enhanced/actions/workflows/test.yml"><img src="https://github.com/PlayBase-OSS/laravel-redis-session-enhanced/actions/workflows/test.yml/badge.svg" alt="Status" /></a>
</p>

A Redis session driver for Laravel that stores rich session metadata — `user_id`, `ip_address`, `user_agent`, and `last_activity` — alongside the session payload. This gives you the same session management capabilities as Laravel's database driver, but backed by Redis.

**Use this if you want to:**
- Track active sessions per user
- Implement "logout from other devices"
- Force-logout users (individually or all at once)
- Prevent concurrent sessions

## Requirements

- PHP `^8.2`
- Laravel `^11.0 | ^12.0`
- Redis configured in your Laravel application

## Installation

```bash
composer require playbase-oss/laravel-redis-session-enhanced
```

The service provider is auto-discovered via Laravel's package discovery.

## Configuration

**1. Add a dedicated Redis connection for sessions in `config/database.php`:**

```php
'redis' => [
    // ... existing connections

    'session' => [
        'host'     => env('REDIS_HOST', '127.0.0.1'),
        'password' => env('REDIS_PASSWORD', null),
        'port'     => env('REDIS_PORT', 6379),
        'database' => env('REDIS_SESSION_DB', 2),
    ],
],
```

Using a separate database index keeps session keys isolated from cache and queue data, making it trivial to flush all sessions without affecting other Redis data.

**2. Update your `.env`:**

```env
SESSION_DRIVER=redis-session
SESSION_CONNECTION=session
```

If you have a cached config, clear it:

```bash
php artisan config:clear
```

## Session Data Structure

Each session is stored as a JSON object with the following shape:

```json
{
    "user_id": 1,
    "ip_address": "127.0.0.1",
    "user_agent": "Mozilla/5.0 ...",
    "last_activity": 1711234567,
    "payload": "<base64-encoded session data>"
}
```

## Usage

### SessionHelper

`SessionHelper` abstracts over both the `redis-session` and `database` drivers, so you can switch between them without changing application code.

```php
use PlayBaseOss\LaravelRedisSessionEnhanced\Support\SessionHelper;

// Get all sessions for a user
SessionHelper::getForUser($userId);

// Get only active sessions (within SESSION_LIFETIME)
SessionHelper::getForUser($userId, only_active: true);

// Delete all sessions of a user except the current one
SessionHelper::deleteForUserExceptSession($userId, request()->session()->id());

// Delete all sessions of a user
SessionHelper::deleteForUserExceptSession($userId);

// Delete all sessions (every user)
SessionHelper::deleteAll();

// Check if the application is using a supported driver
SessionHelper::isUsingValidDriver(); // true for database or redis-session
```

### Direct Handler Access

You can resolve the underlying `RedisSessionEnhancerHandler` directly from the session facade:

```php
use PlayBaseOss\LaravelRedisSessionEnhanced\Session\RedisSessionEnhancerHandler;

/** @var RedisSessionEnhancerHandler $handler */
$handler = Session::getHandler();

// Get all sessions as a Collection of SessionData objects
$sessions = $handler->readAll();

// Flush all session data from Redis
$handler->destroyAll();
```

### SessionData

`readAll()` returns a `Collection` of `SessionData` objects:

```php
use PlayBaseOss\LaravelRedisSessionEnhanced\Session\SessionData;

$session->id;            // string  — session ID
$session->user_id;       // mixed   — authenticated user ID, or null
$session->ip_address;    // string  — client IP address
$session->user_agent;    // string  — client User-Agent (truncated to 500 chars)
$session->last_activity; // int     — Unix timestamp of last activity
$session->payload;       // string  — base64-encoded session payload
```

## Migrating from v1

The package has been transferred to PlayBase-OSS. Update your `composer.json`:

```bash
composer remove craftsys/laravel-redis-session-enhanced
composer require playbase-oss/laravel-redis-session-enhanced
```

Update any direct class references in your application:

| v1 | v2 |
|---|---|
| `Craftsys\LaravelRedisSessionEnhanced\SessionHelper` | `PlayBaseOss\LaravelRedisSessionEnhanced\Support\SessionHelper` |
| `Craftsys\LaravelRedisSessionEnhanced\RedisSessionEnhancerHandler` | `PlayBaseOss\LaravelRedisSessionEnhanced\Session\RedisSessionEnhancerHandler` |

# Credits
- [Craftsys](https://github.com/craftsys/laravel-redis-session-enhanced)

## License

MIT

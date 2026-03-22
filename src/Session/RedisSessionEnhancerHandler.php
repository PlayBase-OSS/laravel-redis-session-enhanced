<?php

namespace PlayBaseOss\LaravelRedisSessionEnhanced\Session;

use Illuminate\Cache\RedisStore;
use Illuminate\Cache\Repository;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Redis\Connections\PhpRedisConnection;
use Illuminate\Redis\Connections\PredisConnection;
use Illuminate\Session\CacheBasedSessionHandler;
use Illuminate\Session\ExistenceAwareInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\InteractsWithTime;
use JsonException;

class RedisSessionEnhancerHandler extends CacheBasedSessionHandler implements ExistenceAwareInterface
{
    use InteractsWithTime;

    protected bool $exists = false;

    public function __construct(
        Repository $cache,
        int $minutes,
        protected ?Container $container = null,
    ) {
        parent::__construct($cache, $minutes);
    }

    public function read($sessionId): string
    {
        $rawData = parent::read($sessionId);

        if (!$rawData) {
            return '';
        }

        $session = $this->parseSessionData($rawData);

        if ($session === null) {
            return '';
        }

        if ($this->expired($session)) {
            $this->exists = true;
            return '';
        }

        if (isset($session['payload'])) {
            $this->exists = true;
            return base64_decode($session['payload'], true) ?: '';
        }

        return '';
    }

    protected function parseSessionData(string $rawData): ?array
    {
        try {
            return json_decode($rawData, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }
    }

    protected function expired(array $session): bool
    {
        return isset($session['last_activity'])
            && $session['last_activity'] < Carbon::now()
                ->subMinutes($this->minutes)
                ->getTimestamp();
    }

    /**
     * @throws BindingResolutionException
     */
    public function write($sessionId, $data): bool
    {
        $payload = $this->getDefaultPayload($data);

        if (!$this->exists) {
            $this->read($sessionId);
        }

        parent::write($sessionId, json_encode($payload));

        return $this->exists = true;
    }

    /**
     * @throws BindingResolutionException
     */
    protected function getDefaultPayload(string $data): array
    {
        $payload = [
            'payload' => base64_encode($data),
            'last_activity' => $this->currentTime(),
        ];

        if (!$this->container) {
            return $payload;
        }

        $this->addUserInformation($payload);
        $this->addRequestInformation($payload);

        return $payload;
    }

    /**
     * @throws BindingResolutionException
     */
    protected function addUserInformation(array &$payload): void
    {
        if ($this->container?->bound(Guard::class)) {
            $payload['user_id'] = $this->userId();
        }
    }

    /**
     * @throws BindingResolutionException
     */
    protected function userId(): mixed
    {
        return $this->container?->make(Guard::class)->id();
    }

    /**
     * @throws BindingResolutionException
     */
    protected function addRequestInformation(array &$payload): void
    {
        if ($this->container?->bound('request')) {
            $payload['ip_address'] = $this->ipAddress();
            $payload['user_agent'] = $this->userAgent();
        }
    }

    /**
     * @throws BindingResolutionException
     */
    protected function ipAddress(): ?string
    {
        return $this->container?->make('request')->ip();
    }

    /**
     * @throws BindingResolutionException
     */
    protected function userAgent(): string
    {
        return mb_substr(
            (string) $this->container?->make('request')->header('User-Agent'),
            0,
            500
        );
    }

    public function setContainer(Application $container): self
    {
        $this->container = $container;
        return $this;
    }

    public function setExists($value): self
    {
        $this->exists = (bool) $value;
        return $this;
    }

    public function readAll(): Collection
    {
        /** @var RedisStore $store */
        $store = $this->cache->getStore();
        $connection = $store->connection();

        $prefix = $this->getFullPrefix($connection, $store);
        $keys = $this->getSessionKeys($connection, $prefix);
        $data = $store->many($keys);

        return collect($data)
            ->filter()
            ->map(fn(string $sessionData, string $sessionId) =>
                $this->parseSessionObject($sessionId, $sessionData)
            )
            ->filter();
    }

    protected function getFullPrefix(mixed $connection, RedisStore $store): string
    {
        $connectionPrefix = match (true) {
            $connection instanceof PhpRedisConnection => $connection->_prefix(''),
            $connection instanceof PredisConnection => $connection->getOptions()->prefix ?: '',
            default => '',
        };

        return $connectionPrefix . $store->getPrefix();
    }

    protected function getSessionKeys(mixed $connection, string $prefix): array
    {
        $keys = $connection->command('keys', ['*']);

        return array_map(
            static fn(string $key) => str_replace($prefix, '', $key),
            is_array($keys) ? $keys : []
        );
    }

    protected function parseSessionObject(string $sessionId, string $data): ?SessionData
    {
        try {
            $parsed = json_decode($data, true, flags: JSON_THROW_ON_ERROR);

            return new SessionData(
                id: $sessionId,
                user_id: $parsed['user_id'] ?? null,
                ip_address: $parsed['ip_address'] ?? '',
                user_agent: $parsed['user_agent'] ?? '',
                last_activity: $parsed['last_activity'] ?? 0,
                payload: $parsed['payload'] ?? '',
            );
        } catch (JsonException) {
            return null;
        }
    }

    public function destroyAll(): bool
    {
        return $this->cache->getStore()->flush();
    }
}

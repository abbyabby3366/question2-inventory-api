<?php

namespace App\Support;

use Closure;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Str;

/**
 * Read-through cache for the product listing.
 *
 * Invalidation uses a version (generation) key instead of cache tags, so it works with every cache
 * driver (database, file, redis, array). Any write to a product, category or supplier rotates the
 * version, and all previously cached pages become unreachable at once and expire by TTL.
 */
class ProductCache
{
    public const VERSION_KEY = 'products:cache-version';

    public const TTL_SECONDS = 600;

    public function __construct(private readonly Repository $cache) {}

    /**
     * @template T
     *
     * @param  array<string, mixed>  $query  validated query parameters that define the result
     * @param  Closure(): T  $resolver
     * @return T
     */
    public function rememberIndex(array $query, Closure $resolver): mixed
    {
        ksort($query);

        $key = sprintf('products:%s:index:%s', $this->version(), sha1(json_encode($query, JSON_THROW_ON_ERROR)));

        return $this->cache->remember($key, self::TTL_SECONDS, $resolver);
    }

    public function flush(): void
    {
        $this->cache->forever(self::VERSION_KEY, (string) Str::uuid());
    }

    private function version(): string
    {
        return $this->cache->rememberForever(self::VERSION_KEY, fn () => (string) Str::uuid());
    }
}


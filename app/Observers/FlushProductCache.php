<?php

namespace App\Observers;

use App\Support\ProductCache;

/**
 * Registered for Product, Category and Supplier: a change to any of them can alter what the
 * product listing returns (embedded category / supplier data), so every write invalidates it.
 */
class FlushProductCache
{
    public function __construct(private readonly ProductCache $cache) {}

    public function saved(): void
    {
        $this->cache->flush();
    }

    public function deleted(): void
    {
        $this->cache->flush();
    }

    public function restored(): void
    {
        $this->cache->flush();
    }

    public function forceDeleted(): void
    {
        $this->cache->flush();
    }
}


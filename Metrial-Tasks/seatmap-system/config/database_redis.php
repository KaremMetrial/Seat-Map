<?php

return [

    /**
     * Redis Clusters for High Availability
     * 
     * Configuration for distributed Redis setup to eliminate single point of failure
     */

    'default' => env('REDIS_CONNECTION', 'primary'),

    'connections' => [

        // Primary Redis for general caching
        'primary' => [
            'url' => env('REDIS_URL'),
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'password' => env('REDIS_PASSWORD', null),
            'port' => env('REDIS_PORT', '6379'),
            'database' => env('REDIS_DB', '0'),
            'read_timeout' => 5,
            'timeout' => 2,
        ],

        // Lock shard 1 - for distributed seat locking
        'redis-lock-1' => [
            'url' => env('REDIS_LOCK_1_URL'),
            'host' => env('REDIS_LOCK_1_HOST', '127.0.0.1'),
            'password' => env('REDIS_LOCK_1_PASSWORD', null),
            'port' => env('REDIS_LOCK_1_PORT', '6380'),
            'database' => env('REDIS_LOCK_1_DB', '1'),
            'read_timeout' => 5,
            'timeout' => 2,
            'persistent' => true, // Use persistent connections for locks
        ],

        // Lock shard 2 - for distributed seat locking
        'redis-lock-2' => [
            'url' => env('REDIS_LOCK_2_URL'),
            'host' => env('REDIS_LOCK_2_HOST', '127.0.0.1'),
            'password' => env('REDIS_LOCK_2_PASSWORD', null),
            'port' => env('REDIS_LOCK_2_PORT', '6381'),
            'database' => env('REDIS_LOCK_2_DB', '2'),
            'read_timeout' => 5,
            'timeout' => 2,
            'persistent' => true,
        ],

        // Lock shard 3 - for distributed seat locking
        'redis-lock-3' => [
            'url' => env('REDIS_LOCK_3_URL'),
            'host' => env('REDIS_LOCK_3_HOST', '127.0.0.1'),
            'password' => env('REDIS_LOCK_3_PASSWORD', null),
            'port' => env('REDIS_LOCK_3_PORT', '6382'),
            'database' => env('REDIS_LOCK_3_DB', '3'),
            'read_timeout' => 5,
            'timeout' => 2,
            'persistent' => true,
        ],

        // Cache Redis (read replicas for seatmap queries)
        'cache' => [
            'url' => env('REDIS_CACHE_URL'),
            'host' => env('REDIS_CACHE_HOST', '127.0.0.1'),
            'password' => env('REDIS_CACHE_PASSWORD', null),
            'port' => env('REDIS_CACHE_PORT', '6383'),
            'database' => env('REDIS_CACHE_DB', '4'),
            'read_timeout' => 5,
            'timeout' => 2,
        ],

        // Sentinel configuration for automatic failover
        'sentinel' => [
            'tcp' => env('REDIS_SENTINEL_HOST', '127.0.0.1'),
            'port' => env('REDIS_SENTINEL_PORT', 26379),
            'timeout' => 5,
        ],
    ],

    /**
     * Redis Sentinel Configuration
     * 
     * Enables automatic failover when master Redis instance fails
     */
    'sentinel' => [
        'active' => env('REDIS_SENTINEL_ACTIVE', false),
        'service' => env('REDIS_SENTINEL_SERVICE', 'seatmap-master'),
        'timeout' => 5,
        'retry_interval' => 100,
    ],

    /**
     * Redis Clustering
     * 
     * Distributes keys across multiple Redis instances
     */
    'clustering' => [
        'enabled' => env('REDIS_CLUSTER_ENABLED', false),
        'strategy' => 'consistent', // consistent hashing
    ],

    /**
     * Redis Options
     */
    'options' => [
        'cluster' => 'redis',
        'prefix' => env('REDIS_PREFIX', 'seatmap:'),
        'serializer' => 'php',
        'compression' => env('REDIS_COMPRESSION', false),
    ],

];

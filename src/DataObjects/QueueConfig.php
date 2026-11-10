<?php

namespace Native\Desktop\DataObjects;

/**
 * @deprecated v3 has no QueueConfig. Its queue workers come from the queue_workers config alone. Nothing changes in v2.
 */
class QueueConfig
{
    /**
     * @param  array<int, string>  $queuesToConsume
     */
    public function __construct(
        public readonly string $alias,
        public readonly array $queuesToConsume,
        public readonly int $memoryLimit,
        public readonly int $timeout,
        public readonly int|float $sleep,
    ) {}

    /**
     * @return array<int, self>
     */
    public static function fromConfigArray(array $config): array
    {
        return array_map(
            function (array|string $worker, string $alias) {
                return new self(
                    $alias,
                    $worker['queues'] ?? ['default'],
                    $worker['memory_limit'] ?? 128,
                    $worker['timeout'] ?? 60,
                    $worker['sleep'] ?? 3,
                );
            },
            $config,
            array_keys($config),
        );
    }
}

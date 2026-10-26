<?php

namespace Native\Desktop\Contracts;

use Native\Desktop\DataObjects\QueueConfig;

/**
 * @deprecated v3 has no QueueWorker contract. Its queue workers come from the queue_workers config alone. Nothing changes in v2.
 */
interface QueueWorker
{
    public function up(string|QueueConfig $config): void;

    public function down(string $alias): void;
}

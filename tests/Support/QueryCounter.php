<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Tests\Support;

use Psr\Log\AbstractLogger;
use Stringable;

/**
 * Doctrine's logging middleware writes one debug line per statement it executes; counting those
 * lines counts the queries a request ran. The counter is static because the test kernel is
 * rebooted between requests, which builds a new instance of this service each time.
 */
class QueryCounter extends AbstractLogger
{
    public static int $count = 0;

    /**
     * @param array<mixed> $context
     */
    public function log($level, string|Stringable $message, array $context = []): void
    {
        if (str_starts_with((string)$message, 'Executing ')) {
            self::$count++;
        }
    }
}

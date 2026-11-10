<?php

/**
 * Copyright since 2026 LW AGENCY LIMITED
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the MIT License
 * that is bundled with this package in the file LICENSE.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/MIT
 *
 * @author    LW AGENCY LIMITED <https://lotuswebagency.com>
 * @copyright Since 2026 LW AGENCY LIMITED
 * @license   https://opensource.org/licenses/MIT MIT License
 */

declare(strict_types=1);

namespace LWA\Kit\Queue;

/**
 * Mirrors LWA\Queues\JobContext for jobs executed inline because lwa_queues is absent.
 */
final class InlineJobContext
{
    public function __construct(private string $handler)
    {
    }

    public function jobId(): int
    {
        return 0;
    }

    public function attempt(): int
    {
        return 1;
    }

    public function maxAttempts(): int
    {
        return 1;
    }

    public function queue(): string
    {
        return 'inline';
    }

    public function release(int $delaySeconds): void
    {
        throw new \RuntimeException(sprintf('Handler "%s" asked to be released in %d s, but it runs inline without lwa_queues', $this->handler, $delaySeconds));
    }

    public function fail(string $reason): void
    {
        throw new \RuntimeException($reason);
    }

    public function log(string $line): void
    {
        if (class_exists(\PrestaShopLogger::class)) {
            \PrestaShopLogger::addLog(sprintf('[lwa inline:%s] %s', $this->handler, $line), 1);
        }
    }
}

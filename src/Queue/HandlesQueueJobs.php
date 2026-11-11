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

use LWA\Kit\Internal\HandlerName;

/**
 * Gives a module main class the lwaQueueHandle() callback that lwa_queues (and the
 * inline fallback) invoke. The module maps handler names to Job classes:
 *
 *   protected function lwaQueueHandlers(): array
 *   {
 *       return ['sync-product' => SyncProductJob::class];
 *   }
 */
trait HandlesQueueJobs
{
    /**
     * @return array<string, class-string<Job>>
     */
    abstract protected function lwaQueueHandlers(): array;

    /**
     * $handler and $payload arrive from another module (lwa_queues, or a caller of
     * dispatchOrRun()): never instantiate a class named directly from that input, only
     * from the fixed lwaQueueHandlers() map this module itself controls.
     *
     * @param array<string, mixed> $payload
     * @param object $context LWA\Queues\JobContext or InlineJobContext, see Job
     *
     * @throws \InvalidArgumentException for a handler name that is not in the map
     * @throws \LogicException when the mapped class does not implement Job
     */
    public function lwaQueueHandle(string $handler, array $payload, object $context): void
    {
        $handlers = $this->lwaQueueHandlers();
        if (!isset($handlers[$handler])) {
            throw new \InvalidArgumentException(sprintf('Unknown queue handler "%s"', HandlerName::sanitize($handler)));
        }

        $class = $handlers[$handler];
        if (!is_subclass_of($class, Job::class)) {
            throw new \LogicException(sprintf('Handler "%s" maps to %s, which does not implement %s', HandlerName::sanitize($handler), $class, Job::class));
        }

        (new $class($payload))->handle($context);
    }
}

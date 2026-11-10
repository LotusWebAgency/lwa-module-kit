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
 * A unit of background work owned by a module. Built from the JSON payload the producer
 * dispatched; $context is LWA\Queues\JobContext when run by lwa_queues and InlineJobContext
 * when run without it. It is typed "object" on purpose: the kit is prefixed per module,
 * so no shared interface can cross the module boundary.
 */
interface Job
{
    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(array $payload);

    public function handle(object $context): void;
}

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

// phpstan-only: HandlesQueueJobs is meant to be consumed by every lwa_ module's main class,
// none of which live in this package, so PHPStan never sees a real user of the trait within
// `paths: [src]` and cannot type-check its method bodies (see
// https://phpstan.org/blog/how-phpstan-analyses-traits). This fixture is that user. Not
// loaded at runtime, not loaded in tests.

namespace LWA\Kit\Stubs;

use LWA\Kit\Queue\HandlesQueueJobs;
use LWA\Kit\Queue\Job;

final class QueueTraitUsageStub extends \Module
{
    use HandlesQueueJobs;

    /** @return array<string, class-string<Job>> */
    protected function lwaQueueHandlers(): array
    {
        return [];
    }
}

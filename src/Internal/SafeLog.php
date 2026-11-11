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

namespace LWA\Kit\Internal;

/**
 * Writes to PrestaShopLogger and never throws. The kit logs from catch blocks and from
 * paths that promise to degrade quietly; PrestaShopLogger::addLog() touches the context,
 * the mailer and the database, any of which may be the thing that just failed.
 *
 * @internal
 */
final class SafeLog
{
    private const SEVERITY_INFO = 1;

    private const SEVERITY_ERROR = 3;

    public static function info(string $message): void
    {
        self::write($message, self::SEVERITY_INFO);
    }

    public static function error(string $message): void
    {
        self::write($message, self::SEVERITY_ERROR);
    }

    private static function write(string $message, int $severity): void
    {
        try {
            if (class_exists(\PrestaShopLogger::class)) {
                \PrestaShopLogger::addLog($message, $severity);
            }
        } catch (\Throwable) {
            // Nowhere left to report to.
        }
    }
}

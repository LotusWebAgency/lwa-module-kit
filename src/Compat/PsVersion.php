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

namespace LWA\Kit\Compat;

final class PsVersion
{
    private static ?string $override = null;

    public static function current(): string
    {
        if (null !== self::$override) {
            return self::$override;
        }

        return defined('_PS_VERSION_') ? (string) constant('_PS_VERSION_') : '0.0.0';
    }

    public static function major(): int
    {
        return (int) explode('.', self::current())[0];
    }

    public static function atLeast(string $version): bool
    {
        return version_compare(self::current(), $version, '>=');
    }

    public static function isNine(): bool
    {
        return self::major() >= 9;
    }

    /**
     * Tests only. Production code reads _PS_VERSION_.
     */
    public static function overrideForTests(?string $version): void
    {
        self::$override = $version;
    }
}

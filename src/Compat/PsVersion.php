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

use LWA\Kit\Internal\PsConstant;

/**
 * The running PrestaShop version, for the few places a module must branch on it.
 * Reports "0.0.0" outside PrestaShop.
 */
final class PsVersion
{
    private static ?string $override = null;

    public static function current(): string
    {
        if (null !== self::$override) {
            return self::$override;
        }

        return defined('_PS_VERSION_') ? PsConstant::string('_PS_VERSION_') : '0.0.0';
    }

    public static function major(): int
    {
        return (int) explode('.', self::current())[0];
    }

    /**
     * @param string $version a version_compare() operand, e.g. "8.2.0"
     */
    public static function atLeast(string $version): bool
    {
        return version_compare(self::current(), $version, '>=');
    }

    public static function isNine(): bool
    {
        return self::major() >= 9;
    }

    /**
     * @internal test seam; pass null to read _PS_VERSION_ again
     */
    public static function overrideForTests(?string $version): void
    {
        self::$override = $version;
    }
}

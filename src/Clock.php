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

namespace LWA\Kit;

/**
 * Storage is always UTC `DATETIME` (`Y-m-d H:i:s`); shop time exists only at display.
 * This is the single place a module converts between the two.
 *
 * Round-tripping a value this class produced always holds: toUtc(toShopTime($utc)) === $utc,
 * because converting a UTC instant to a shop timezone and back always resolves the same
 * instant unambiguously.
 *
 * The reverse is not guaranteed for an arbitrary shop-time string, because civil clocks are
 * not a bijection with instants around a DST transition:
 * - Spring-forward gap: a wall-clock time that never occurred (e.g. 02:30 in a timezone that
 *   jumps 02:00 -> 03:00) is normalised forward by PHP, shifted by the size of the gap. So
 *   toShopTime(toUtc('2026-03-08 02:30:00')) returns '2026-03-08 03:30:00', not the input.
 * - Autumn ambiguous hour: a wall-clock time that occurs twice (e.g. 01:30 in a timezone that
 *   falls back 02:00 -> 01:00) is resolved by PHP to its first occurrence, the earlier (DST)
 *   UTC offset. That choice is applied consistently by both directions, so
 *   toShopTime(toUtc('2026-11-01 01:30:00')) does return the original string.
 */
final class Clock
{
    public const FORMAT = 'Y-m-d H:i:s';

    private static ?string $frozenUtc = null;

    public function __construct(private \DateTimeZone $shopTimezone)
    {
    }

    public static function forShop(): self
    {
        $name = \Configuration::get('PS_TIMEZONE');

        try {
            $timezone = new \DateTimeZone(is_string($name) && '' !== $name ? $name : 'UTC');
        } catch (\Exception) {
            $timezone = new \DateTimeZone('UTC');
        }

        return new self($timezone);
    }

    public static function utcNow(): string
    {
        if (null !== self::$frozenUtc) {
            return self::$frozenUtc;
        }

        return (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format(self::FORMAT);
    }

    public function toShopTime(string $utcDateTime, string $format = self::FORMAT): string
    {
        return (new \DateTimeImmutable($utcDateTime, new \DateTimeZone('UTC')))
            ->setTimezone($this->shopTimezone)
            ->format($format);
    }

    public function toUtc(string $shopDateTime): string
    {
        return (new \DateTimeImmutable($shopDateTime, $this->shopTimezone))
            ->setTimezone(new \DateTimeZone('UTC'))
            ->format(self::FORMAT);
    }

    /**
     * @internal test seam; pass null to unfreeze
     */
    public static function freezeForTests(?string $utc): void
    {
        self::$frozenUtc = $utc;
    }
}

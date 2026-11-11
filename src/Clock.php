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
 * Input is strict: exactly `Y-m-d H:i:s` and a real calendar date. An empty string, a
 * relative phrase ("tomorrow"), an embedded UTC offset, `0000-00-00 00:00:00` or
 * February 31 raise \InvalidArgumentException instead of quietly becoming another instant.
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

    /**
     * A clock in the shop's PS_TIMEZONE, or UTC when that is unset or not a timezone.
     */
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

    /**
     * The current instant in the storage format.
     */
    public static function utcNow(): string
    {
        if (null !== self::$frozenUtc) {
            return self::$frozenUtc;
        }

        return (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format(self::FORMAT);
    }

    /**
     * @param string $utcDateTime a stored value, `Y-m-d H:i:s` in UTC
     * @param string $format a date() format for the result
     *
     * @throws \InvalidArgumentException when $utcDateTime is not a valid `Y-m-d H:i:s`
     */
    public function toShopTime(string $utcDateTime, string $format = self::FORMAT): string
    {
        return self::parse($utcDateTime, new \DateTimeZone('UTC'))
            ->setTimezone($this->shopTimezone)
            ->format($format);
    }

    /**
     * @param string $shopDateTime `Y-m-d H:i:s` on the shop's wall clock
     *
     * @throws \InvalidArgumentException when $shopDateTime is not a valid `Y-m-d H:i:s`
     */
    public function toUtc(string $shopDateTime): string
    {
        return self::parse($shopDateTime, $this->shopTimezone)
            ->setTimezone(new \DateTimeZone('UTC'))
            ->format(self::FORMAT);
    }

    private static function parse(string $dateTime, \DateTimeZone $timezone): \DateTimeImmutable
    {
        $parsed = \DateTimeImmutable::createFromFormat('!' . self::FORMAT, $dateTime, $timezone);
        // A rolled-over date (February 31) parses, and is reported only as a warning.
        $errors = \DateTimeImmutable::getLastErrors();

        if (false === $parsed || (false !== $errors && $errors['warning_count'] > 0)) {
            throw new \InvalidArgumentException(sprintf('"%s" is not a valid %s date-time', preg_replace('/[^\x20-\x7E]/', '', substr($dateTime, 0, 32)), self::FORMAT));
        }

        return $parsed;
    }

    /**
     * @internal test seam; pass null to unfreeze
     */
    public static function freezeForTests(?string $utc): void
    {
        self::$frozenUtc = $utc;
    }
}

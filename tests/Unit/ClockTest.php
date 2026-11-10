<?php

declare(strict_types=1);

namespace LWA\Kit\Tests\Unit;

use LWA\Kit\Clock;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ClockTest extends TestCase
{
    protected function tearDown(): void
    {
        Clock::freezeForTests(null);
        \Configuration::reset();
    }

    public function testUtcNowHasTheStorageFormat(): void
    {
        self::assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', Clock::utcNow());
    }

    public function testFrozenNow(): void
    {
        Clock::freezeForTests('2026-09-08 10:00:00');

        self::assertSame('2026-09-08 10:00:00', Clock::utcNow());
        self::assertSame('2026-09-08 10:00:00', Clock::utcNow());
    }

    public function testUnfreezeRestoresRealTime(): void
    {
        Clock::freezeForTests('2026-09-08 10:00:00');
        Clock::freezeForTests(null);

        self::assertNotSame('2026-09-08 10:00:00', Clock::utcNow());
    }

    public function testConvertsBothWays(): void
    {
        $clock = new Clock(new \DateTimeZone('America/New_York'));

        self::assertSame('2026-01-15 07:30:00', $clock->toShopTime('2026-01-15 12:30:00'));
        self::assertSame('15/01/2026 07:30', $clock->toShopTime('2026-01-15 12:30:00', 'd/m/Y H:i'));
        self::assertSame('2026-01-15 12:30:00', $clock->toUtc('2026-01-15 07:30:00'));
    }

    public function testForShopReadsConfigurationAndFallsBackToUtc(): void
    {
        // The ambient default is deliberately not UTC: a fallback that read
        // date_default_timezone_get() instead of hard-coding UTC would pass this
        // test on a UTC runner, which is what CI runs on.
        $ambient = date_default_timezone_get();
        date_default_timezone_set('America/New_York');

        try {
            \Configuration::updateValue('PS_TIMEZONE', 'Europe/Riga');
            self::assertSame('2026-06-01 15:00:00', Clock::forShop()->toShopTime('2026-06-01 12:00:00'));

            \Configuration::updateValue('PS_TIMEZONE', 'Not/AZone');
            self::assertSame('2026-06-01 12:00:00', Clock::forShop()->toShopTime('2026-06-01 12:00:00'));

            \Configuration::reset();
            self::assertSame('2026-06-01 12:00:00', Clock::forShop()->toShopTime('2026-06-01 12:00:00'));
        } finally {
            date_default_timezone_set($ambient);
        }
    }

    /**
     * @return list<array{0: string, 1: \DateTimeZone}>
     */
    public static function utcRoundTripProvider(): array
    {
        return [
            'ordinary time' => ['2026-01-15 12:30:00', new \DateTimeZone('America/New_York')],
            'UTC shop timezone' => ['2026-01-15 12:30:00', new \DateTimeZone('UTC')],
            'instant of the spring-forward transition' => ['2026-03-08 07:30:00', new \DateTimeZone('America/New_York')],
            'instant of the autumn ambiguous hour' => ['2026-11-01 05:30:00', new \DateTimeZone('America/New_York')],
        ];
    }

    #[DataProvider('utcRoundTripProvider')]
    public function testToUtcOfToShopTimeAlwaysReturnsTheOriginalUtcValue(string $utc, \DateTimeZone $shopTimezone): void
    {
        $clock = new Clock($shopTimezone);

        self::assertSame($utc, $clock->toUtc($clock->toShopTime($utc)));
    }

    public function testSpringForwardGapIsNormalisedForward(): void
    {
        $clock = new Clock(new \DateTimeZone('America/New_York'));

        // 2026-03-08 02:00 -> 03:00 is the DST gap in America/New_York: 02:30 never happened.
        $utc = $clock->toUtc('2026-03-08 02:30:00');

        self::assertSame('2026-03-08 07:30:00', $utc);
        self::assertSame('2026-03-08 03:30:00', $clock->toShopTime($utc));
    }

    public function testAutumnAmbiguousHourResolvesToItsFirstOccurrence(): void
    {
        $clock = new Clock(new \DateTimeZone('America/New_York'));

        // 2026-11-01 02:00 -> 01:00 is the DST fallback in America/New_York: 01:30 happens
        // twice. PHP resolves it to the earlier (DST, -04:00) offset, and that choice is
        // stable in both directions.
        $utc = $clock->toUtc('2026-11-01 01:30:00');

        self::assertSame('2026-11-01 05:30:00', $utc);
        self::assertSame('2026-11-01 01:30:00', $clock->toShopTime($utc));
    }
}

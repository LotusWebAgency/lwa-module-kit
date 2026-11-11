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
     * @return array<string, array{0: string}>
     */
    public static function invalidDateTimeProvider(): array
    {
        return [
            'empty string' => [''],
            'relative phrase' => ['tomorrow'],
            'embedded offset' => ['2026-01-01 00:00:00+05:00'],
            'rolled-over day' => ['2026-02-31 10:00:00'],
            'zero date' => ['0000-00-00 00:00:00'],
            'date only' => ['2026-01-01'],
            'trailing newline' => ["2026-01-01 00:00:00\n"],
        ];
    }

    #[DataProvider('invalidDateTimeProvider')]
    public function testToShopTimeRejectsAnythingButAStoredDateTime(string $input): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new Clock(new \DateTimeZone('Europe/Riga')))->toShopTime($input);
    }

    #[DataProvider('invalidDateTimeProvider')]
    public function testToUtcRejectsAnythingButAWallClockDateTime(string $input): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new Clock(new \DateTimeZone('Europe/Riga')))->toUtc($input);
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function shopTimezoneProvider(): array
    {
        return [
            'Moscow, no DST' => ['Europe/Moscow', '2026-07-01 15:00:00'],
            'Paris, summer' => ['Europe/Paris', '2026-07-01 14:00:00'],
            'Kolkata, half-hour offset' => ['Asia/Kolkata', '2026-07-01 17:30:00'],
            'Kathmandu, 45-minute offset' => ['Asia/Kathmandu', '2026-07-01 17:45:00'],
            'Tokyo' => ['Asia/Tokyo', '2026-07-01 21:00:00'],
            'Sydney, southern winter' => ['Australia/Sydney', '2026-07-01 22:00:00'],
            'Los Angeles, previous day' => ['America/Los_Angeles', '2026-07-01 05:00:00'],
            'Kiritimati, next day' => ['Pacific/Kiritimati', '2026-07-02 02:00:00'],
        ];
    }

    #[DataProvider('shopTimezoneProvider')]
    public function testConvertsForShopsAroundTheWorld(string $timezone, string $expected): void
    {
        $clock = new Clock(new \DateTimeZone($timezone));

        self::assertSame($expected, $clock->toShopTime('2026-07-01 12:00:00'));
        self::assertSame('2026-07-01 12:00:00', $clock->toUtc($expected));
    }

    public function testOutputDoesNotDependOnTheProcessLocale(): void
    {
        $previous = setlocale(\LC_ALL, '0');
        $applied = setlocale(\LC_ALL, ['tr_TR.UTF-8', 'ru_RU.UTF-8', 'de_DE.UTF-8', 'fr_FR.UTF-8']);

        try {
            self::assertSame(
                'Wednesday 01 July 2026 15:00',
                (new Clock(new \DateTimeZone('Europe/Moscow')))->toShopTime('2026-07-01 12:00:00', 'l d F Y H:i'),
                false === $applied ? 'no non-English locale installed; ran under the default' : (string) $applied
            );
        } finally {
            setlocale(\LC_ALL, (string) $previous);
        }
    }

    public function testTheRejectedValueIsQuotedCleanAndCapped(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('"0123456789abcdefFORGED01234567" is not a valid Y-m-d H:i:s date-time');
        (new Clock(new \DateTimeZone('UTC')))->toShopTime("0123456789abcdef\nFORGED\x7F0123456789tail" /* cut at 32 bytes, then stripped */);
    }

    /**
     * @return array<string, array{0: string, 1: \DateTimeZone}>
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

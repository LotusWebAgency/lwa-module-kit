<?php

declare(strict_types=1);

namespace LWA\Kit\Tests\Unit\Compat;

use LWA\Kit\Compat\PsVersion;
use PHPUnit\Framework\TestCase;

final class PsVersionTest extends TestCase
{
    protected function tearDown(): void
    {
        PsVersion::overrideForTests(null);
    }

    public function testReportsZeroWhenPrestaShopConstantIsMissing(): void
    {
        self::assertSame('0.0.0', PsVersion::current());
        self::assertSame(0, PsVersion::major());
        self::assertFalse(PsVersion::atLeast('8.1.0'));
    }

    public function testMajorAndComparisonOnEight(): void
    {
        PsVersion::overrideForTests('8.2.8');

        self::assertSame('8.2.8', PsVersion::current());
        self::assertSame(8, PsVersion::major());
        self::assertTrue(PsVersion::atLeast('8.1.0'));
        self::assertTrue(PsVersion::atLeast('8.2.8'));
        self::assertFalse(PsVersion::atLeast('8.2.9'));
        self::assertFalse(PsVersion::isNine());
    }

    public function testNineIsDetected(): void
    {
        PsVersion::overrideForTests('9.1.5');

        self::assertSame(9, PsVersion::major());
        self::assertTrue(PsVersion::isNine());
        self::assertTrue(PsVersion::atLeast('9.0.0'));
    }
}

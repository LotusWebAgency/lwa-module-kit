<?php

declare(strict_types=1);

namespace LWA\Kit\Tests\Unit\Security;

use LWA\Kit\Security\Token;
use PHPUnit\Framework\TestCase;

final class TokenTest extends TestCase
{
    public function testGeneratesHexOfRequestedLength(): void
    {
        $token = Token::generate();

        self::assertSame(48, strlen($token));
        self::assertMatchesRegularExpression('/^[0-9a-f]{48}$/', $token);
        self::assertSame(64, strlen(Token::generate(32)));
    }

    public function testTwoTokensDiffer(): void
    {
        self::assertNotSame(Token::generate(), Token::generate());
    }

    public function testRefusesShortTokens(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Token::generate(8);
    }

    public function testSixteenBytesIsTheFloor(): void
    {
        self::assertSame(32, strlen(Token::generate(16)));

        $this->expectException(\InvalidArgumentException::class);
        Token::generate(15);
    }

    public function testEqualsIsStrictAndRejectsEmptyExpected(): void
    {
        self::assertTrue(Token::equals('abc', 'abc'));
        self::assertFalse(Token::equals('abc', 'abd'));
        self::assertFalse(Token::equals('abc', ''));
        self::assertFalse(Token::equals('', ''));
        self::assertFalse(Token::equals('', 'anything'));
        self::assertFalse(Token::equals('abc', 'ABC'));

        // PHP's loose comparison treats numeric strings numerically ('1e2' == '100' is
        // true, '0e1' == '0e2' is true); hash_equals does not. These two pairs fail the
        // moment equals() is swapped for ==.
        self::assertFalse(Token::equals('1e2', '100'));
        self::assertFalse(Token::equals('0e1', '0e2'));
    }
}

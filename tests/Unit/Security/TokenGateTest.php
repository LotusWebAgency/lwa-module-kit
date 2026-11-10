<?php

declare(strict_types=1);

namespace LWA\Kit\Tests\Unit\Security;

use LWA\Kit\Security\Token;
use LWA\Kit\Security\TokenGate;
use PHPUnit\Framework\TestCase;

final class TokenGateTest extends TestCase
{
    protected function setUp(): void
    {
        \Configuration::reset();
    }

    public function testPassesOnlyWithTheExpectedToken(): void
    {
        $token = Token::generate();
        $gate = new TokenGate($token);

        self::assertTrue($gate->passes($token, 'apache2handler'));
        self::assertFalse($gate->passes(Token::generate(), 'apache2handler'));
        self::assertFalse($gate->passes('', 'apache2handler'));
        self::assertFalse($gate->passes(null, 'apache2handler'));
    }

    public function testEmptyExpectedTokenNeverPasses(): void
    {
        $gate = new TokenGate('');

        self::assertFalse($gate->passes('', 'apache2handler'));
        self::assertFalse($gate->passes(null, 'apache2handler'));
    }

    public function testConfiguredTokenShorterThanMinimumNeverPasses(): void
    {
        $short = str_repeat('a', TokenGate::MIN_TOKEN_LENGTH - 1);
        $gate = new TokenGate($short);

        self::assertFalse($gate->passes($short, 'apache2handler'));
    }

    public function testMinTokenLengthIsThirtyTwo(): void
    {
        // 32 is the length of the shortest token Token::generate() can produce at its
        // documented 16-byte floor (bin2hex of 16 bytes = 32 hex chars); the two values
        // are coupled and must move together.
        self::assertSame(32, TokenGate::MIN_TOKEN_LENGTH);
    }

    public function testMinTokenLengthBoundary(): void
    {
        $atFloor = str_repeat('a', TokenGate::MIN_TOKEN_LENGTH);
        $belowFloor = str_repeat('a', TokenGate::MIN_TOKEN_LENGTH - 1);

        self::assertTrue((new TokenGate($atFloor))->passes($atFloor, 'apache2handler'));
        self::assertFalse((new TokenGate($belowFloor))->passes($belowFloor, 'apache2handler'));
    }

    public function testCliIsRefusedUnlessAllowed(): void
    {
        $gate = new TokenGate(Token::generate());

        self::assertFalse($gate->passes(null, 'cli'));
        self::assertTrue($gate->allowCli()->passes(null, 'cli'));
        self::assertFalse($gate->allowCli()->passes(null, 'apache2handler'));
        self::assertFalse($gate->passes(null, 'cli'), 'allowCli returns a copy and leaves the original strict');
    }

    public function testCliExemptionRequiresNoWebRequestInFlight(): void
    {
        $hadRequestMethod = array_key_exists('REQUEST_METHOD', $_SERVER);
        $previous = $_SERVER['REQUEST_METHOD'] ?? null;
        $_SERVER['REQUEST_METHOD'] = 'GET';

        try {
            $gate = (new TokenGate(Token::generate()))->allowCli();

            self::assertFalse($gate->passes(null, 'cli'), 'a cli SAPI report with a live web request must still be denied');
        } finally {
            if ($hadRequestMethod) {
                $_SERVER['REQUEST_METHOD'] = $previous;
            } else {
                unset($_SERVER['REQUEST_METHOD']);
            }
        }
    }

    public function testReadsTheExpectedTokenFromConfiguration(): void
    {
        $token = Token::generate();
        \Configuration::updateValue('LWA_TEMPLATE_TOKEN', $token);

        self::assertTrue(TokenGate::fromConfiguration('LWA_TEMPLATE_TOKEN')->passes($token, 'apache2handler'));
        self::assertFalse(TokenGate::fromConfiguration('LWA_MISSING')->passes('anything', 'apache2handler'));
    }

    public function testDenyPayload(): void
    {
        self::assertSame(['error' => 'forbidden'], (new TokenGate(Token::generate()))->denyPayload());
    }
}

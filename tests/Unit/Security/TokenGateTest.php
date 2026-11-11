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

        self::assertTrue($gate->passes($token));
        self::assertFalse($gate->passes(Token::generate()));
        self::assertFalse($gate->passes(''));
        self::assertFalse($gate->passes(null));
    }

    public function testEmptyExpectedTokenNeverPasses(): void
    {
        $gate = new TokenGate('');

        self::assertFalse($gate->passes(''));
        self::assertFalse($gate->passes(null));
    }

    public function testConfiguredTokenShorterThanMinimumNeverPasses(): void
    {
        $short = str_repeat('a', TokenGate::MIN_TOKEN_LENGTH - 1);
        $gate = new TokenGate($short);

        self::assertFalse($gate->passes($short));
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

        self::assertTrue((new TokenGate($atFloor))->passes($atFloor));
        self::assertFalse((new TokenGate($belowFloor))->passes($belowFloor));
    }

    public function testCliIsRefusedUnlessAllowed(): void
    {
        $gate = new TokenGate(Token::generate());

        self::assertFalse($gate->passes(null));
        self::assertTrue($gate->allowCli()->passes(null));
        self::assertFalse($gate->passes(null), 'allowCli returns a copy and leaves the original strict');
    }

    public function testCliExemptionRequiresNoWebRequestInFlight(): void
    {
        $hadRequestMethod = array_key_exists('REQUEST_METHOD', $_SERVER);
        $previous = $_SERVER['REQUEST_METHOD'] ?? null;
        $_SERVER['REQUEST_METHOD'] = 'GET';

        try {
            $gate = (new TokenGate(Token::generate()))->allowCli();

            self::assertFalse($gate->passes(null), 'a cli SAPI with a live web request must still be denied');
        } finally {
            if ($hadRequestMethod) {
                $_SERVER['REQUEST_METHOD'] = $previous;
            } else {
                unset($_SERVER['REQUEST_METHOD']);
            }
        }
    }

    /**
     * The exemption is for the cli SAPI only. php-cgi and phpdbg started from a shell
     * also have no web request in flight, and must still be refused.
     */
    public function testCliExemptionIsRefusedUnderAnotherSapiWithoutAWebRequest(): void
    {
        $binaries = array_filter(
            [dirname(\PHP_BINARY) . '/php-cgi', dirname(\PHP_BINARY) . '/phpdbg'],
            'is_executable'
        );
        if ([] === $binaries) {
            self::markTestSkipped('needs php-cgi or phpdbg next to ' . \PHP_BINARY);
        }

        foreach ($binaries as $binary) {
            $command = str_ends_with($binary, 'phpdbg')
                ? [$binary, '-qrr', __DIR__ . '/../../Fixtures/token-gate-sapi.php']
                : [$binary, '-q', __DIR__ . '/../../Fixtures/token-gate-sapi.php'];
            $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, []);
            self::assertIsResource($process);
            $output = trim((string) stream_get_contents($pipes[1]));
            proc_close($process);

            self::assertMatchesRegularExpression('/^(cgi-fcgi|phpdbg):no-request:closed$/', $output, basename($binary));
        }
    }

    public function testATokenInAnyScriptIsComparedByteForByte(): void
    {
        $token = Token::generate();
        $gate = new TokenGate($token);

        foreach (['пароль', '密码', 'كلمة السر', '🔑', "\xFF\xFE", $token . 'é', mb_strtoupper($token)] as $provided) {
            self::assertFalse($gate->passes($provided), $provided);
        }

        $unicode = str_repeat('ключ密🔑', 8);
        self::assertTrue((new TokenGate($unicode))->passes($unicode));
    }

    public function testReadsTheExpectedTokenFromConfiguration(): void
    {
        $token = Token::generate();
        \Configuration::updateValue('LWA_TEMPLATE_TOKEN', $token);

        self::assertTrue(TokenGate::fromConfiguration('LWA_TEMPLATE_TOKEN')->passes($token));
        self::assertFalse(TokenGate::fromConfiguration('LWA_MISSING')->passes('anything'));
    }

    public function testDenyPayload(): void
    {
        self::assertSame(['error' => 'forbidden'], (new TokenGate(Token::generate()))->denyPayload());
    }
}

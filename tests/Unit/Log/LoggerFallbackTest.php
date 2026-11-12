<?php

declare(strict_types=1);

namespace LWA\Kit\Tests\Unit\Log;

use LWA\Kit\Log\Logger;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

/**
 * No LWA\Logger\Api is loaded here, so every call takes the PrestaShopLogger fallback.
 * Separate processes because Logger memoizes availability per process.
 */
final class LoggerFallbackTest extends TestCase
{
    private function freshState(): void
    {
        \Module::reset();
        \PrestaShopLogger::reset();
        Logger::forget();
    }

    #[RunInSeparateProcess]
    public function testWarningAndAboveFallBackWithDuplicatesAllowedAndBelowIsDropped(): void
    {
        $this->freshState();

        $log = Logger::for('lwa_example');
        $log->debug('a.b', 'x');
        $log->info('a.b', 'x');
        $log->notice('a.b', 'x');
        $log->warning('sync.skipped', 'Feed row has no price', ['row' => 3]);
        $log->error('sync.failed', 'Timeout');
        $log->critical('c.c');
        $log->alert('a.a');
        $log->emergency('e.e');
        $log->log('Warn', 'alias.used');

        self::assertSame([
            ['message' => '[lwa_example] sync.skipped: Feed row has no price', 'severity' => 2, 'allowDuplicate' => true],
            ['message' => '[lwa_example] sync.failed: Timeout', 'severity' => 3, 'allowDuplicate' => true],
            ['message' => '[lwa_example] c.c', 'severity' => 4, 'allowDuplicate' => true],
            ['message' => '[lwa_example] a.a', 'severity' => 4, 'allowDuplicate' => true],
            ['message' => '[lwa_example] e.e', 'severity' => 4, 'allowDuplicate' => true],
            ['message' => '[lwa_example] alias.used', 'severity' => 2, 'allowDuplicate' => true],
        ], \PrestaShopLogger::$logs);
        self::assertFalse(Logger::available());
        self::assertNull(Logger::unavailableReason());
        self::assertTrue($log->enabled('emergency'));
        self::assertNull($log->requestId());
    }

    #[RunInSeparateProcess]
    public function testEnabledFollowsTheFallbackWhenUnavailable(): void
    {
        $this->freshState();

        $log = Logger::for('lwa_example');
        self::assertTrue($log->enabled('warning'));
        self::assertTrue($log->enabled('WARN'));
        self::assertTrue($log->enabled('error', 'journey'));
        self::assertTrue($log->enabled(' Fatal '));
        self::assertFalse($log->enabled('info'));
        self::assertFalse($log->enabled('debug'));
        self::assertFalse($log->enabled('notice'));
        self::assertFalse($log->enabled('loud'));
        self::assertSame([], \PrestaShopLogger::$logs);
    }

    #[RunInSeparateProcess]
    public function testSeverityAliasesAndUnknownSeverities(): void
    {
        $this->freshState();

        $log = Logger::for('lwa_example');
        $log->log(' ERR ', 'a.a');
        $log->log('crit', 'b.b');
        $log->log('emerg', 'c.c');
        $log->log('fatal', 'd.d');
        $log->log('bogus', 'e.e');

        self::assertSame([3, 4, 4, 4], array_column(\PrestaShopLogger::$logs, 'severity'));
    }

    #[RunInSeparateProcess]
    public function testFallbackMessageHasNoCharactersCoreValidationRejects(): void
    {
        $this->freshState();

        Logger::for('lwa_example')->warning('x.y', "<b>{a}</b>\nnext");

        self::assertSame('[lwa_example] x.y: (b)(a)(/b) next', \PrestaShopLogger::$logs[0]['message']);
    }

    #[RunInSeparateProcess]
    public function testFallbackKeepsMultibyteUtf8Intact(): void
    {
        $this->freshState();

        Logger::for('lwa_example')->warning('x.y', 'Fält Åre ą Ѕ');

        $message = \PrestaShopLogger::$logs[0]['message'];
        self::assertSame('[lwa_example] x.y: Fält Åre ą Ѕ', $message);
        self::assertTrue(mb_check_encoding($message, 'UTF-8'));
    }

    #[RunInSeparateProcess]
    public function testFallbackMessageIsCutTo1000Characters(): void
    {
        $this->freshState();

        Logger::for('lwa_example')->error('x.y', str_repeat('я', 2000));

        self::assertSame(1000, mb_strlen(\PrestaShopLogger::$logs[0]['message']));
    }

    #[RunInSeparateProcess]
    public function testNothingEscapesWhenPrestaShopLoggerThrows(): void
    {
        $this->freshState();

        \PrestaShopLogger::$throw = new \RuntimeException('db down');
        Logger::for('lwa_example')->emergency('x.y');

        $this->addToAssertionCount(1);
    }
}

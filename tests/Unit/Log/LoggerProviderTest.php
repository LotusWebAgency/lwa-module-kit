<?php

declare(strict_types=1);

namespace LWA\Kit\Tests\Unit\Log;

use LWA\Kit\Log\Logger;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

/**
 * Each test runs in its own process: LWA\Logger\Api is process-global and Logger memoizes
 * availability, so a test picks its provider double before touching Logger at all.
 */
final class LoggerProviderTest extends TestCase
{
    private function installProvider(): void
    {
        \Module::reset();
        \PrestaShopLogger::reset();
        Logger::forget();

        $provider = new \Module();
        $provider->name = 'lwa_logger';
        \Module::$enabled['lwa_logger'] = true;
        \Module::$instances['lwa_logger'] = $provider;
    }

    #[RunInSeparateProcess]
    public function testForwardsEveryArgumentAndSkipsTheFallback(): void
    {
        require_once __DIR__ . '/../../Fakes/Logger/LwaLoggerApi.php';
        $this->installProvider();

        Logger::for('lwa_example')->warning('sync.skipped', 'm', ['a' => 1], ['type' => 'journey']);

        self::assertSame([['module' => 'lwa_example', 'severity' => 'warning', 'event' => 'sync.skipped', 'message' => 'm', 'context' => ['a' => 1], 'options' => ['type' => 'journey']]], \LWA\Logger\Api::$logged);
        self::assertSame([], \PrestaShopLogger::$logs);
        self::assertSame('01M750SMBV04HMASW9NF6YY093', Logger::for('lwa_example')->requestId());
        self::assertTrue(Logger::available());
        self::assertNull(Logger::unavailableReason());
    }

    #[RunInSeparateProcess]
    public function testLowSeveritiesReachTheProviderUntouched(): void
    {
        require_once __DIR__ . '/../../Fakes/Logger/LwaLoggerApi.php';
        $this->installProvider();

        Logger::for('lwa_example')->log('Warn', 'alias.used');
        Logger::for('lwa_example')->debug('d.d');

        self::assertSame(['Warn', 'debug'], array_column(\LWA\Logger\Api::$logged, 'severity'));
        self::assertSame([], \PrestaShopLogger::$logs);
    }

    #[RunInSeparateProcess]
    public function testEnabledIsAskedOfTheProvider(): void
    {
        require_once __DIR__ . '/../../Fakes/Logger/LwaLoggerApi.php';
        $this->installProvider();

        $log = Logger::for('lwa_example');
        self::assertTrue($log->enabled('debug'));
        \LWA\Logger\Api::$enabledAnswer = false;
        self::assertFalse($log->enabled('debug', 'journey'));
    }

    #[RunInSeparateProcess]
    public function testProviderThrowingFallsBackForWarningAndAbove(): void
    {
        require_once __DIR__ . '/../../Fakes/Logger/LwaLoggerApi.php';
        $this->installProvider();
        \LWA\Logger\Api::$throw = new \RuntimeException('logger db down');

        $log = Logger::for('lwa_example');
        $log->error('sync.failed', 'Timeout');
        $log->info('sync.ok');

        $fallbackRows = array_values(array_filter(\PrestaShopLogger::$logs, static fn (array $row): bool => $row['allowDuplicate']));
        self::assertCount(1, $fallbackRows);
        self::assertSame(3, $fallbackRows[0]['severity']);
        self::assertSame('[lwa_example] sync.failed: Timeout', $fallbackRows[0]['message']);
        self::assertNull($log->requestId());
        self::assertTrue($log->enabled('error'));
    }

    #[RunInSeparateProcess]
    public function testEnabledThrowingFollowsTheFallbackRule(): void
    {
        require_once __DIR__ . '/../../Fakes/Logger/LwaLoggerApi.php';
        $this->installProvider();
        \LWA\Logger\Api::$throw = new \RuntimeException('logger db down');

        $log = Logger::for('lwa_example');
        self::assertTrue($log->enabled('warning'));
        self::assertTrue($log->enabled('emergency'));
        self::assertFalse($log->enabled('info'));
        self::assertFalse($log->enabled('loud'));
    }

    #[RunInSeparateProcess]
    public function testVersionMismatchIsUnavailableAndLoggedOnce(): void
    {
        require_once __DIR__ . '/../../Fakes/Logger/LwaLoggerApiSupportsVersionFalse.php';
        $this->installProvider();

        $log = Logger::for('lwa_example');
        $log->warning('a.a');
        $log->warning('b.b');

        self::assertFalse(Logger::available());
        self::assertSame('lwa_logger speaks an API version this kit was not built against (kit requires API 1)', Logger::unavailableReason());
        self::assertCount(3, \PrestaShopLogger::$logs);
        self::assertSame(3, \PrestaShopLogger::$logs[0]['severity']);
        self::assertFalse(\PrestaShopLogger::$logs[0]['allowDuplicate']);
        self::assertSame(2, \PrestaShopLogger::$logs[1]['severity']);
        self::assertSame(2, \PrestaShopLogger::$logs[2]['severity']);
    }

    #[RunInSeparateProcess]
    public function testThrowingSupportsVersionDegrades(): void
    {
        require_once __DIR__ . '/../../Fakes/Logger/LwaLoggerApiThrowingSupportsVersion.php';
        $this->installProvider();

        Logger::for('lwa_example')->error('a.a');

        self::assertFalse(Logger::available());
        self::assertStringStartsWith('lwa_logger failed to load: ', (string) Logger::unavailableReason());
        self::assertCount(2, \PrestaShopLogger::$logs);
    }

    #[RunInSeparateProcess]
    public function testProbeFailureDegrades(): void
    {
        $this->installProvider();
        \Module::$throwOnGetInstance = new \RuntimeException('missing scoped autoload');

        Logger::for('lwa_example')->error('a.a');

        self::assertFalse(Logger::available());
        self::assertStringStartsWith('lwa_logger failed to load: ', (string) Logger::unavailableReason());
        self::assertCount(2, \PrestaShopLogger::$logs);
        self::assertSame(3, \PrestaShopLogger::$logs[0]['severity']);
        self::assertFalse(\PrestaShopLogger::$logs[0]['allowDuplicate']);
    }

    #[RunInSeparateProcess]
    public function testAvailabilityIsMemoized(): void
    {
        require_once __DIR__ . '/../../Fakes/Logger/LwaLoggerApi.php';
        $this->installProvider();

        self::assertTrue(Logger::available());
        \Module::$enabled['lwa_logger'] = false;
        self::assertTrue(Logger::available());
        Logger::forget();
        self::assertFalse(Logger::available());
    }
}

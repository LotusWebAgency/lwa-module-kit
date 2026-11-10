<?php

declare(strict_types=1);

namespace LWA\Kit\Tests\Unit\Queue;

use LWA\Kit\Queue\Queue;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

/**
 * class_exists() results (and any caching Queue could add around them) are process-global:
 * once tests/Fakes/Queues/LwaQueuesApi.php has been required anywhere in a process (as
 * QueueTest.php does, for the whole rest of this suite), LWA\Queues\Api is defined for
 * the rest of that process and Queue::available() can never honestly see it as absent
 * there again. Each test here therefore runs in its own process and requires (or doesn't
 * require) the double it needs before touching Queue at all.
 */
final class QueueAvailabilityTest extends TestCase
{
    private function installQueueModule(): void
    {
        $provider = new \Module();
        $provider->name = 'lwa_queues';
        \Module::$enabled['lwa_queues'] = true;
        \Module::$instances['lwa_queues'] = $provider;
    }

    #[RunInSeparateProcess]
    public function testUnavailableWhenTheApiClassNeverLoadedEvenIfTheModuleReportsEnabled(): void
    {
        \Module::reset();
        $this->installQueueModule();

        self::assertFalse(class_exists(\LWA\Queues\Api::class, false));
        self::assertFalse(Queue::available());
        self::assertNull(Queue::dispatch('lwa_fixture', 'record', ['id' => 1]));
        self::assertFalse(class_exists(\LWA\Queues\Api::class, false), 'available() must not autoload-fatal or force-load the class');
    }

    #[RunInSeparateProcess]
    public function testTheOrdinarySteadyStatesStaySilent(): void
    {
        // The mirror of the version-mismatch test above: "lwa_queues is not installed"
        // and "installed but disabled" are legitimate configurations, not faults. If
        // they ever start logging, every page of every shop without the queue gets a
        // log line per dispatch.
        \Module::reset();
        \PrestaShopLogger::reset();

        self::assertFalse(Queue::available());
        self::assertNull(Queue::dispatch('lwa_fixture', 'record', ['id' => 1]));

        $provider = new \Module();
        $provider->name = 'lwa_queues';
        \Module::$instances['lwa_queues'] = $provider;

        self::assertFalse(Queue::available());
        self::assertNull(Queue::dispatch('lwa_fixture', 'record', ['id' => 2]));

        self::assertSame([], \PrestaShopLogger::$logs);
        self::assertNull(Queue::unavailableReason());
    }

    #[RunInSeparateProcess]
    public function testUnavailableWhenSupportsVersionIsAnInstanceMethod(): void
    {
        // method_exists() also reports instance methods, and calling one statically
        // raises an Error. Degrading to the exact-match check must not blow up.
        require_once __DIR__ . '/../../Fakes/Queues/LwaQueuesApiNonStaticSupportsVersion.php';

        \Module::reset();
        $this->installQueueModule();

        self::assertTrue(method_exists(\LWA\Queues\Api::class, 'supportsVersion'));
        self::assertFalse(Queue::available());
    }

    #[RunInSeparateProcess]
    public function testUnavailableWhenSupportsVersionThrows(): void
    {
        require_once __DIR__ . '/../../Fakes/Queues/LwaQueuesApiThrowingSupportsVersion.php';

        \Module::reset();
        \PrestaShopLogger::reset();
        $this->installQueueModule();

        self::assertFalse(Queue::available());
        self::assertNotNull(Queue::unavailableReason());
        self::assertCount(1, \PrestaShopLogger::$logs);
    }

    #[RunInSeparateProcess]
    public function testUnavailableWhenSupportsVersionReturnsSomethingTruthyButNotTrue(): void
    {
        require_once __DIR__ . '/../../Fakes/Queues/LwaQueuesApiTruthySupportsVersion.php';

        \Module::reset();
        $this->installQueueModule();

        self::assertFalse(Queue::available(), 'a truthy non-true answer must not read as compatible');
    }

    #[RunInSeparateProcess]
    public function testUnavailableWhenTheApiIsANewerVersionThanThisKitWasBuiltAgainstAndWithoutSupportsVersion(): void
    {
        require_once __DIR__ . '/../../Fakes/Queues/LwaQueuesApiV2.php';

        \Module::reset();
        $this->installQueueModule();

        self::assertSame(2, \LWA\Queues\Api::API_VERSION);
        self::assertFalse(method_exists(\LWA\Queues\Api::class, 'supportsVersion'));
        self::assertFalse(Queue::available());
        self::assertNull(Queue::dispatch('lwa_fixture', 'record', ['id' => 1]));

        // A version mismatch is a misconfiguration, not the ordinary "not installed"
        // steady state: it gets exactly one log line per request and an explanation.
        self::assertNull(Queue::dispatch('lwa_fixture', 'record', ['id' => 2]));
        self::assertCount(1, \PrestaShopLogger::$logs);
        self::assertSame(3, \PrestaShopLogger::$logs[0]['severity']);
        self::assertNotNull(Queue::unavailableReason());
    }

    #[RunInSeparateProcess]
    public function testAvailableWhenTheProviderDeclaresSupportForAnOlderVersionThroughSupportsVersion(): void
    {
        require_once __DIR__ . '/../../Fakes/Queues/LwaQueuesApiSupportsVersionTrue.php';

        \Module::reset();
        $this->installQueueModule();

        // Exact match on API_VERSION alone would say "unavailable" (2 !== 1); the
        // provider's own supportsVersion(1) === true must win instead.
        self::assertSame(2, \LWA\Queues\Api::API_VERSION);
        self::assertTrue(Queue::available());
        self::assertNull(Queue::unavailableReason());
        self::assertSame(999, Queue::dispatch('lwa_fixture', 'record', ['id' => 1]));
    }

    #[RunInSeparateProcess]
    public function testUnavailableWhenTheProviderDeclinesSupportThroughSupportsVersionEvenIfApiVersionMatches(): void
    {
        require_once __DIR__ . '/../../Fakes/Queues/LwaQueuesApiSupportsVersionFalse.php';

        \Module::reset();
        $this->installQueueModule();

        // API_VERSION alone would exact-match (1 === 1); supportsVersion(1) === false
        // must still win, proving the method is consulted rather than used as a mere
        // fallback tiebreaker.
        self::assertSame(1, \LWA\Queues\Api::API_VERSION);
        self::assertFalse(Queue::available());
        self::assertNull(Queue::dispatch('lwa_fixture', 'record', ['id' => 1]));
    }
}

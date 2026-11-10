<?php

declare(strict_types=1);

namespace LWA\Kit\Tests\Unit\Queue;

use LWA\Kit\Queue\Queue;
use LWA\Queues\Api;
use PHPUnit\Framework\TestCase;

// LWA\Queues\Api is not autoloaded by bootstrap.php's flat tests/Fakes/*.php glob (see
// tests/Fakes/Queues/LwaQueuesApi.php) so that Queue::available() can be honestly tested
// against a process where it never loaded at all (QueueAvailabilityTest). This class needs
// the normal, version-1 double for every test below, so it requires it once up front.
require_once __DIR__ . '/../../Fakes/Queues/LwaQueuesApi.php';

final class QueueTest extends TestCase
{
    protected function setUp(): void
    {
        \Module::reset();
        Api::reset();
        \PrestaShopLogger::reset();
        RecordingJob::$handled = [];
        self::resetQueueLogDedup();
    }

    private function installQueueModule(): void
    {
        $provider = new \Module();
        $provider->name = 'lwa_queues';
        \Module::$enabled['lwa_queues'] = true;
        \Module::$instances['lwa_queues'] = $provider;
    }

    /**
     * Queue's "log this once per request" flags are static and would otherwise leak
     * between test methods running in the same process (only QueueAvailabilityTest's
     * #[RunInSeparateProcess] tests are naturally immune to that).
     */
    private static function resetQueueLogDedup(): void
    {
        $reflection = new \ReflectionClass(Queue::class);
        foreach (['unavailableReason', 'loggedProviderProbeFailure', 'loggedVersionMismatch'] as $name) {
            $reflection->getProperty($name)->setValue(null, 'unavailableReason' === $name ? null : false);
        }
    }

    public function testUnavailableWhenTheQueueModuleIsDisabled(): void
    {
        self::assertFalse(Queue::available());
        self::assertNull(Queue::dispatch('lwa_fixture', 'record', ['id' => 1]));
        self::assertSame([], Api::$dispatched);
    }

    public function testUnavailableWhenInstalledButDisabled(): void
    {
        $provider = new \Module();
        $provider->name = 'lwa_queues';
        \Module::$instances['lwa_queues'] = $provider;
        // Deliberately not setting \Module::$enabled['lwa_queues']: getInstanceByName()
        // alone must not be enough to make the queue available.

        self::assertFalse(Queue::available());
    }

    public function testUnavailableWhenEnabledButTheModuleFilesAreGone(): void
    {
        // The mirror of the case above, and a real production state: the module row says
        // enabled but the files were removed, so getInstanceByName() returns false rather
        // than a \Module. This is what pins the `instanceof \Module` guard in available().
        \Module::$enabled['lwa_queues'] = true;

        self::assertFalse(Queue::available());
    }

    public function testAvailableReturnsFalseInsteadOfPropagatingWhenTheProviderConstructorThrows(): void
    {
        \Module::$enabled['lwa_queues'] = true;
        \Module::$throwOnGetInstance = new \RuntimeException('missing scoped autoload');

        self::assertFalse(Queue::available());
        self::assertNull(Queue::dispatch('lwa_fixture', 'record', ['id' => 1]));
        self::assertSame([], Api::$dispatched);
        self::assertCount(1, \PrestaShopLogger::$logs);
        self::assertStringContainsString('missing scoped autoload', \PrestaShopLogger::$logs[0]['message']);
        self::assertSame(3, \PrestaShopLogger::$logs[0]['severity']);
    }

    public function testDispatchesThroughTheApiWhenAvailable(): void
    {
        $this->installQueueModule();

        self::assertTrue(Queue::available());
        self::assertSame(42, Queue::dispatch('lwa_fixture', 'record', ['id' => 1], ['queue' => 'high']));
        self::assertSame('lwa_fixture', Api::$dispatched[0]['module']);
        self::assertSame(['queue' => 'high'], Api::$dispatched[0]['options']);
    }

    public function testApiExceptionsBecomeNullAndAreLogged(): void
    {
        $this->installQueueModule();
        Api::$throw = new \RuntimeException('db gone');

        self::assertNull(Queue::dispatch('lwa_fixture', 'record'));
        self::assertStringContainsString('db gone', \PrestaShopLogger::$logs[0]['message']);
        self::assertSame(3, \PrestaShopLogger::$logs[0]['severity']);
    }

    public function testDispatchOrRunQueuesWhenPossible(): void
    {
        $this->installQueueModule();

        self::assertSame(42, Queue::dispatchOrRun(new ModuleWithJobs(), 'record', ['id' => 2]));
        self::assertSame([], RecordingJob::$handled);
    }

    public function testDispatchOrRunRunsInlineWithoutTheQueue(): void
    {
        self::assertNull(Queue::dispatchOrRun(new ModuleWithJobs(), 'record', ['id' => 3]));
        self::assertSame([['id' => 3, 'queue' => 'inline']], RecordingJob::$handled);
    }

    public function testDispatchOrRunDoesNotDoubleRunWhenTheQueueWasAvailableButDeclinedTheJob(): void
    {
        $this->installQueueModule();
        Api::$throw = new \RuntimeException('lock timeout');

        self::assertNull(Queue::dispatchOrRun(new ModuleWithJobs(), 'record', ['id' => 4]));
        // Must NOT run inline: the queue may already have accepted/partially processed
        // this job before failing, so running it again here would double-execute it.
        self::assertSame([], RecordingJob::$handled);
        self::assertCount(2, \PrestaShopLogger::$logs);
        self::assertStringContainsString('lock timeout', \PrestaShopLogger::$logs[0]['message']);
        self::assertStringContainsString('declined', \PrestaShopLogger::$logs[1]['message']);
        self::assertSame(3, \PrestaShopLogger::$logs[1]['severity']);
    }

    public function testDispatchOrRunRequiresTheCallback(): void
    {
        $plain = new \Module();
        $plain->name = 'lwa_plain';

        $this->expectException(\LogicException::class);
        Queue::dispatchOrRun($plain, 'record');
    }

    public function testDispatchOrRunPropagatesAnInlineFailureInsteadOfSwallowingIt(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('boom: no queue');
        Queue::dispatchOrRun(new ModuleWithJobs(), 'failing', ['reason' => 'no queue']);
    }
}

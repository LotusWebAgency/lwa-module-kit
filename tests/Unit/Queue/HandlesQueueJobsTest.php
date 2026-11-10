<?php

declare(strict_types=1);

namespace LWA\Kit\Tests\Unit\Queue;

use LWA\Kit\Queue\HandlesQueueJobs;
use LWA\Kit\Queue\InlineJobContext;
use LWA\Kit\Queue\Job;
use PHPUnit\Framework\TestCase;

final class RecordingJob implements Job
{
    /** @var list<array<string, mixed>> */
    public static array $handled = [];

    /** @param array<string, mixed> $payload */
    public function __construct(private array $payload)
    {
    }

    public function handle(object $context): void
    {
        self::$handled[] = $this->payload + ['queue' => $context->queue()];
    }
}

final class FailingJob implements Job
{
    /** @param array<string, mixed> $payload */
    public function __construct(private array $payload)
    {
    }

    public function handle(object $context): void
    {
        $context->fail('boom: ' . ($this->payload['reason'] ?? 'unspecified'));
    }
}

final class NotAJob
{
    /** @param array<string, mixed> $payload */
    public function __construct(array $payload)
    {
    }
}

final class ModuleWithJobs extends \Module
{
    use HandlesQueueJobs;

    public string $name = 'lwa_fixture';

    /** @return array<string, class-string> */
    protected function lwaQueueHandlers(): array
    {
        return ['record' => RecordingJob::class, 'broken' => NotAJob::class, 'failing' => FailingJob::class];
    }
}

final class HandlesQueueJobsTest extends TestCase
{
    protected function setUp(): void
    {
        RecordingJob::$handled = [];
    }

    public function testDispatchesToTheMappedJobClass(): void
    {
        (new ModuleWithJobs())->lwaQueueHandle('record', ['id' => 7], new InlineJobContext('record'));

        self::assertSame([['id' => 7, 'queue' => 'inline']], RecordingJob::$handled);
    }

    public function testUnknownHandlerThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('nope');
        (new ModuleWithJobs())->lwaQueueHandle('nope', [], new InlineJobContext('nope'));
    }

    public function testMappedClassMustImplementJob(): void
    {
        $this->expectException(\LogicException::class);
        (new ModuleWithJobs())->lwaQueueHandle('broken', [], new InlineJobContext('broken'));
    }

    public function testAFailingHandlerPropagatesInsteadOfBeingSwallowed(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('boom: db gone');
        (new ModuleWithJobs())->lwaQueueHandle('failing', ['reason' => 'db gone'], new InlineJobContext('failing'));
    }
}

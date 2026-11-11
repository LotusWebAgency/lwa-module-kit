<?php

declare(strict_types=1);

namespace LWA\Kit\Tests\Unit\Queue;

use LWA\Kit\Queue\HandlesQueueJobs;
use LWA\Kit\Queue\InlineJobContext;
use LWA\Kit\Queue\Job;
use PHPUnit\Framework\Attributes\DataProvider;
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

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function handlerNameInManyScriptsProvider(): array
    {
        return [
            'Russian' => ['синхронизация-товара', 'синхронизация-товара'],
            'Chinese' => ['同步产品', '同步产品'],
            'Japanese' => ['商品を同期する', '商品を同期する'],
            'Arabic, right to left' => ['مزامنة المنتج', 'مزامنة المنتج'],
            'Hebrew with a bidi override' => ["סנכרון\u{202E}evil", "סנכרון\u{202E}evil"],
            'Turkish dotted and dotless i' => ['İıŞşĞğ', 'İıŞşĞğ'],
            'German sharp s' => ['Straße', 'Straße'],
            'emoji, four bytes each' => [str_repeat('🚚', 64), str_repeat('🚚', 64)],
            'emoji past the cap' => [str_repeat('🚚', 70), str_repeat('🚚', 64) . '…'],
            'Russian with a forged line' => ["задача\nFORGED\u{2029}строка", 'задачаFORGEDстрока'],
            'Thai combining marks past the cap' => [str_repeat('กำ', 40), mb_substr(str_repeat('กำ', 40), 0, 64, 'UTF-8') . '…'],
        ];
    }

    #[DataProvider('handlerNameInManyScriptsProvider')]
    public function testHandlerNamesInAnyLanguageStayValidUtf8(string $handler, string $expected): void
    {
        try {
            (new ModuleWithJobs())->lwaQueueHandle($handler, [], new \stdClass());
            self::fail('an unknown handler must throw');
        } catch (\InvalidArgumentException $e) {
            self::assertSame('Unknown queue handler "' . $expected . '"', $e->getMessage());
            self::assertTrue(mb_check_encoding($e->getMessage(), 'UTF-8'));
        }
    }

    public function testAPayloadInAnyLanguageReachesTheJobUntouched(): void
    {
        $payload = ['ru' => 'Привет', 'zh' => '你好', 'ar' => 'مرحبا', 'emoji' => '👋', 'tr' => 'İstanbul'];

        (new ModuleWithJobs())->lwaQueueHandle('record', $payload, new InlineJobContext('record'));

        self::assertSame([$payload + ['queue' => 'inline']], RecordingJob::$handled);
    }

    public function testAnUnknownHandlerNameIsSanitizedBeforeItReachesTheMessage(): void
    {
        $forged = "x\nFORGED\u{2028}LINE\0" . str_repeat('a', 100);

        try {
            (new ModuleWithJobs())->lwaQueueHandle($forged, [], new \stdClass());
            self::fail('an unknown handler must throw');
        } catch (\InvalidArgumentException $e) {
            self::assertSame('Unknown queue handler "xFORGEDLINE' . str_repeat('a', 53) . '…"', $e->getMessage());
        }

        foreach ([str_repeat('é', 64), 'naïve-' . str_repeat('é', 30)] as $kept) {
            try {
                (new ModuleWithJobs())->lwaQueueHandle($kept, [], new \stdClass());
                self::fail('an unknown handler must throw');
            } catch (\InvalidArgumentException $e) {
                self::assertSame('Unknown queue handler "' . $kept . '"', $e->getMessage(), 'valid UTF-8 up to 64 characters is kept whole');
            }
        }

        try {
            (new ModuleWithJobs())->lwaQueueHandle(str_repeat('é', 65), [], new \stdClass());
            self::fail('an unknown handler must throw');
        } catch (\InvalidArgumentException $e) {
            self::assertSame('Unknown queue handler "' . str_repeat('é', 64) . '…"', $e->getMessage(), 'the cut falls on a character boundary');
        }

        try {
            (new ModuleWithJobs())->lwaQueueHandle("bad\xFFutf8\n", [], new \stdClass());
            self::fail('an unknown handler must throw');
        } catch (\InvalidArgumentException $e) {
            self::assertSame('Unknown queue handler "badutf8"', $e->getMessage());
        }
    }
}

<?php

declare(strict_types=1);

namespace LWA\Kit\Tests\Unit\Queue;

use LWA\Kit\Queue\InlineJobContext;
use PHPUnit\Framework\TestCase;

final class InlineJobContextTest extends TestCase
{
    protected function setUp(): void
    {
        \PrestaShopLogger::reset();
    }

    public function testDescribesAnInlineRun(): void
    {
        $context = new InlineJobContext('ping');

        self::assertSame(0, $context->jobId());
        self::assertSame(1, $context->attempt());
        self::assertSame(1, $context->maxAttempts());
        self::assertSame('inline', $context->queue());
    }

    public function testReleaseIsImpossibleInline(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('ping');
        (new InlineJobContext('ping'))->release(30);
    }

    public function testFailThrowsTheReason(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('bad input');
        (new InlineJobContext('ping'))->fail('bad input');
    }

    public function testLogGoesToPrestaShopLogger(): void
    {
        (new InlineJobContext('ping'))->log('hello');

        self::assertSame('[lwa inline:ping] hello', \PrestaShopLogger::$logs[0]['message']);

        (new InlineJobContext('ping'))->log('Готово: 你好 مرحبا 🚚');
        self::assertSame('[lwa inline:ping] Готово: 你好 مرحبا 🚚', \PrestaShopLogger::$logs[1]['message']);
    }
}

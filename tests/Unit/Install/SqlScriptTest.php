<?php

declare(strict_types=1);

namespace LWA\Kit\Tests\Unit\Install;

use LWA\Kit\Install\SqlScript;
use PHPUnit\Framework\TestCase;

final class SqlScriptTest extends TestCase
{
    public function testSubstitutesPrefixAndEngine(): void
    {
        $sql = 'CREATE TABLE `PREFIX_x` (a INT) ENGINE=ENGINE_TYPE';

        self::assertSame('CREATE TABLE `ps_x` (a INT) ENGINE=InnoDB', SqlScript::substitute($sql, 'ps_', 'InnoDB'));
    }

    public function testSplitsOnStatementEndsAndDropsCommentsAndBlankChunks(): void
    {
        $sql = "-- header\nCREATE TABLE a (x INT);\n\n-- second\nINSERT INTO a VALUES ('semi; colon');\nDROP TABLE b;";

        self::assertSame(
            ['CREATE TABLE a (x INT)', "INSERT INTO a VALUES ('semi; colon')", 'DROP TABLE b'],
            SqlScript::split($sql)
        );
    }

    public function testLoadReadsAndSubstitutes(): void
    {
        $statements = SqlScript::load(__DIR__ . '/../../Fixtures/sql/install.sql', 'ps_', 'InnoDB');

        self::assertCount(2, $statements);
        self::assertStringStartsWith('CREATE TABLE IF NOT EXISTS `ps_lwa_fixture_item`', $statements[0]);
        self::assertStringContainsString('ENGINE=InnoDB', $statements[0]);
        self::assertStringStartsWith('INSERT INTO `ps_lwa_fixture_item`', $statements[1]);
    }

    public function testLoadThrowsWhenFileIsMissing(): void
    {
        $this->expectException(\RuntimeException::class);
        SqlScript::load(__DIR__ . '/nope.sql', 'ps_', 'InnoDB');
    }
}

<?php

declare(strict_types=1);

namespace LWA\Kit\Tests\Unit\Install;

use LWA\Kit\Install\SqlScript;
use PHPUnit\Framework\Attributes\DataProvider;
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
        $sql = "-- header\nCREATE TABLE a (x INT);\n\n-- second\nINSERT INTO a VALUES ('semi; colon');\n  DROP TABLE b ; ";

        self::assertSame(
            ['CREATE TABLE a (x INT)', "INSERT INTO a VALUES ('semi; colon')", 'DROP TABLE b'],
            SqlScript::split($sql)
        );
    }

    public function testTextInAnyLanguageSurvivesSubstitutionAndSplitting(): void
    {
        $sql = "-- комментарий 注释\n"
            . "INSERT INTO `PREFIX_t` (`name`) VALUES ('Привет; мир'), ('你好；世界'), ('مرحبا'), ('İstanbul 🚚');\r\n"
            . "UPDATE `PREFIX_t` SET `name` = 'Größe' WHERE `name` = 'naïve';\n";

        self::assertSame(
            [
                "INSERT INTO `ps_t` (`name`) VALUES ('Привет; мир'), ('你好；世界'), ('مرحبا'), ('İstanbul 🚚')",
                "UPDATE `ps_t` SET `name` = 'Größe' WHERE `name` = 'naïve'",
            ],
            SqlScript::split(SqlScript::substitute($sql, 'ps_', 'InnoDB'))
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

    public function testLoadThrowsWhenTheFileHoldsNoStatement(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('holds no statement');
        SqlScript::load(__DIR__ . '/../../Fixtures/sql/empty.sql', 'ps_', 'InnoDB');
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function scriptThatExhaustsPcreProvider(): array
    {
        return [
            'while splitting statements' => ["CREATE TABLE a (x INT);\nDROP TABLE a;\n"],
            'while dropping comments' => ["   -x;\n"],
        ];
    }

    #[DataProvider('scriptThatExhaustsPcreProvider')]
    public function testSplitThrowsInsteadOfReturningAPartialListWhenPcreFails(string $sql): void
    {
        $jit = ini_get('pcre.jit');
        $limit = ini_get('pcre.backtrack_limit');
        ini_set('pcre.jit', '0');
        ini_set('pcre.backtrack_limit', '1');

        try {
            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage('Cannot split the SQL script');
            SqlScript::split($sql);
        } finally {
            ini_set('pcre.jit', (string) $jit);
            ini_set('pcre.backtrack_limit', (string) $limit);
        }
    }

    public function testLoadThrowsWhenFileIsMissing(): void
    {
        $this->expectException(\RuntimeException::class);
        SqlScript::load(__DIR__ . '/nope.sql', 'ps_', 'InnoDB');
    }
}

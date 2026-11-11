<?php

/**
 * Copyright since 2026 LW AGENCY LIMITED
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the MIT License
 * that is bundled with this package in the file LICENSE.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/MIT
 *
 * @author    LW AGENCY LIMITED <https://lotuswebagency.com>
 * @copyright Since 2026 LW AGENCY LIMITED
 * @license   https://opensource.org/licenses/MIT MIT License
 */

declare(strict_types=1);

namespace LWA\Kit\Install;

/**
 * Turns an install.sql / uninstall.sql file into a list of executable statements.
 *
 * Authoring contract:
 * - Every statement ends with ";" followed by a newline. A statement may span lines.
 * - Comments are full-line "--" only. No trailing "-- comment", no "#", no block comments.
 * - No newline inside a string literal.
 * - "PREFIX_" and "ENGINE_TYPE" are replaced wherever they appear, string literals
 *   included, with the shop's table prefix and storage engine.
 */
final class SqlScript
{
    public static function substitute(string $sql, string $prefix, string $engine): string
    {
        return str_replace(['PREFIX_', 'ENGINE_TYPE'], [$prefix, $engine], $sql);
    }

    /**
     * Splits on ";" followed by a line break, the same rule PrestaShop's installer uses,
     * and drops "--" comment lines and empty chunks.
     *
     * @return list<string>
     *
     * @throws \RuntimeException when PCRE fails on the script (e.g. invalid input or a
     *                           backtrack limit), rather than returning a partial list
     */
    public static function split(string $sql): array
    {
        $withoutComments = preg_replace('/^\s*--.*$/m', '', $sql);
        $chunks = null === $withoutComments ? false : preg_split('/;\s*[\r\n]+/', $withoutComments);
        if (!is_array($chunks)) {
            throw new \RuntimeException(sprintf('Cannot split the SQL script: %s', preg_last_error_msg()));
        }

        $statements = [];
        foreach ($chunks as $chunk) {
            $chunk = trim($chunk, "; \t\n\r\0\x0B");
            if ('' !== $chunk) {
                $statements[] = $chunk;
            }
        }

        return $statements;
    }

    /**
     * @return non-empty-list<string>
     *
     * @throws \RuntimeException when the file is unreadable or holds no statement: a
     *                           script that runs nothing is a packaging error, not a success
     */
    public static function load(string $path, string $prefix, string $engine): array
    {
        $sql = is_readable($path) ? file_get_contents($path) : false;
        if (false === $sql) {
            throw new \RuntimeException(sprintf('Cannot read SQL file "%s"', $path));
        }

        $statements = self::split(self::substitute($sql, $prefix, $engine));
        if ([] === $statements) {
            throw new \RuntimeException(sprintf('SQL file "%s" holds no statement', $path));
        }

        return $statements;
    }
}

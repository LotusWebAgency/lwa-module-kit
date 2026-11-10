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

final class SqlScript
{
    public static function substitute(string $sql, string $prefix, string $engine): string
    {
        return str_replace(['PREFIX_', 'ENGINE_TYPE'], [$prefix, $engine], $sql);
    }

    /**
     * Splits on ";" followed by a line break, the same rule PrestaShop's installer uses,
     * drops "--" comment lines and empty chunks.
     *
     * Contract callers must meet: every statement ends with ";" followed by a newline.
     * A statement may span as many lines as it likes. No inline trailing "-- comment"
     * (full-line "--" comments are fine); no newlines inside string literals.
     *
     * @return list<string>
     */
    public static function split(string $sql): array
    {
        $withoutComments = preg_replace('/^\s*--.*$/m', '', $sql) ?? $sql;
        $statements = [];
        foreach (preg_split('/;\s*[\r\n]+/', $withoutComments) ?: [] as $chunk) {
            $chunk = trim(rtrim(trim($chunk), ';'));
            if ('' !== $chunk) {
                $statements[] = $chunk;
            }
        }

        return $statements;
    }

    /**
     * @return list<string>
     */
    public static function load(string $path, string $prefix, string $engine): array
    {
        $sql = is_readable($path) ? file_get_contents($path) : false;
        if (false === $sql) {
            throw new \RuntimeException(sprintf('Cannot read SQL file "%s"', $path));
        }

        return self::split(self::substitute($sql, $prefix, $engine));
    }
}

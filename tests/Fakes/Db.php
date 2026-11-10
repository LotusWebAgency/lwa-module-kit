<?php

declare(strict_types=1);

if (!class_exists('Db', false)) {
    final class Db
    {
        /** @var list<string> */
        public static array $executed = [];

        /** @var list<string> substrings; a statement containing one fails */
        public static array $failOn = [];

        public static function reset(): void
        {
            self::$executed = [];
            self::$failOn = [];
        }

        public static function getInstance(bool $master = true): Db
        {
            return new self();
        }

        public function execute(string $sql, bool $useCache = true): bool
        {
            self::$executed[] = $sql;
            foreach (self::$failOn as $needle) {
                if (str_contains($sql, $needle)) {
                    return false;
                }
            }

            return true;
        }
    }
}

<?php

declare(strict_types=1);

if (!class_exists('Configuration', false)) {
    /**
     * Test double for the PrestaShop Configuration class.
     */
    final class Configuration
    {
        /** @var array<string, string> */
        public static array $values = [];

        public static function reset(): void
        {
            self::$values = [];
        }

        /** @return string|false */
        public static function get(string $key, ?int $idLang = null, ?int $idShopGroup = null, ?int $idShop = null, $default = false)
        {
            return self::$values[$key] ?? $default;
        }

        public static function hasKey(string $key, ?int $idLang = null, ?int $idShopGroup = null, ?int $idShop = null): bool
        {
            return array_key_exists($key, self::$values);
        }

        /** @param mixed $values */
        public static function updateValue(string $key, $values, bool $html = false, ?int $idShopGroup = null, ?int $idShop = null): bool
        {
            self::$values[$key] = (string) $values;

            return true;
        }

        public static function deleteByName(string $key): bool
        {
            unset(self::$values[$key]);

            return true;
        }
    }
}

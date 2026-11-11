<?php

declare(strict_types=1);

if (!class_exists('Configuration', false)) {
    /**
     * Test double for the PrestaShop Configuration class.
     */
    final class Configuration
    {
        /** @var array<string, string> values in the current shop context */
        public static array $values = [];

        /** @var array<string, string> shop-wide values */
        public static array $global = [];

        /** @var list<string> keys whose global write fails */
        public static array $failOn = [];

        public static function reset(): void
        {
            self::$values = [];
            self::$global = [];
            self::$failOn = [];
        }

        /** @return string|false */
        public static function get(string $key, ?int $idLang = null, ?int $idShopGroup = null, ?int $idShop = null, $default = false)
        {
            return self::$values[$key] ?? self::$global[$key] ?? $default;
        }

        public static function hasKey(string $key, ?int $idLang = null, ?int $idShopGroup = null, ?int $idShop = null): bool
        {
            // Like the core: with no shop ids, only the global scope is consulted.
            return array_key_exists($key, self::$global);
        }

        /** @param mixed $values */
        public static function updateValue(string $key, $values, bool $html = false, ?int $idShopGroup = null, ?int $idShop = null): bool
        {
            self::$values[$key] = (string) $values;

            return true;
        }

        /** @param mixed $values */
        public static function updateGlobalValue(string $key, $values, bool $html = false): bool
        {
            if (in_array($key, self::$failOn, true)) {
                return false;
            }

            self::$global[$key] = (string) $values;

            return true;
        }

        public static function deleteByName(string $key): bool
        {
            unset(self::$values[$key], self::$global[$key]);

            return true;
        }
    }
}

<?php

declare(strict_types=1);

if (!class_exists('PrestaShopLogger', false)) {
    final class PrestaShopLogger
    {
        /** @var list<array{message: string, severity: int, allowDuplicate: bool}> */
        public static array $logs = [];

        public static ?Throwable $throw = null;

        public static function reset(): void
        {
            self::$logs = [];
            self::$throw = null;
        }

        public static function addLog(string $message, int $severity = 1, ?int $errorCode = null, ?string $objectType = null, ?int $objectId = null, bool $allowDuplicate = false, ?int $idEmployee = null): bool
        {
            if (null !== self::$throw) {
                throw self::$throw;
            }

            self::$logs[] = ['message' => $message, 'severity' => $severity, 'allowDuplicate' => $allowDuplicate];

            return true;
        }
    }
}

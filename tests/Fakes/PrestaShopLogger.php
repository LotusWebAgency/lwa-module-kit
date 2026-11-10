<?php

declare(strict_types=1);

if (!class_exists('PrestaShopLogger', false)) {
    final class PrestaShopLogger
    {
        /** @var list<array{message: string, severity: int}> */
        public static array $logs = [];

        public static function reset(): void
        {
            self::$logs = [];
        }

        public static function addLog(string $message, int $severity = 1, ?int $errorCode = null, ?string $objectType = null, ?int $objectId = null, bool $allowDuplicate = false, ?int $idEmployee = null): bool
        {
            self::$logs[] = ['message' => $message, 'severity' => $severity];

            return true;
        }
    }
}

<?php

declare(strict_types=1);

namespace LWA\Logger;

// Deliberately NOT under tests/Fakes/*.php, for the reason tests/Fakes/Queues/LwaQueuesApi.php
// documents: bootstrap.php autoloads that flat glob, and an always-present LWA\Logger\Api
// would make the "provider never loaded" path impossible to exercise. Required explicitly,
// only by the tests that need it. Never required alongside another tests/Fakes/Logger/*.php
// fixture: all of them declare LWA\Logger\Api.
if (!class_exists(Api::class, false)) {
    /**
     * Test double for the API that lwa_logger ships.
     */
    final class Api
    {
        public const API_VERSION = 1;

        /** @var list<array{module: string, severity: string, event: string, message: string, context: array<string, mixed>, options: array<string, mixed>}> */
        public static array $logged = [];

        public static bool $enabledAnswer = true;

        public static string $requestId = '01M750SMBV04HMASW9NF6YY093';

        public static ?\Throwable $throw = null;

        public static function reset(): void
        {
            self::$logged = [];
            self::$enabledAnswer = true;
            self::$requestId = '01M750SMBV04HMASW9NF6YY093';
            self::$throw = null;
        }

        public static function supportsVersion(int $v): bool
        {
            return 1 === $v;
        }

        /**
         * @param array<string, mixed> $context
         * @param array<string, mixed> $options
         */
        public static function log(string $module, string $severity, string $event, string $message = '', array $context = [], array $options = []): void
        {
            if (null !== self::$throw) {
                throw self::$throw;
            }
            self::$logged[] = ['module' => $module, 'severity' => $severity, 'event' => $event, 'message' => $message, 'context' => $context, 'options' => $options];
        }

        public static function enabled(string $severity, string $type = 'module'): bool
        {
            if (null !== self::$throw) {
                throw self::$throw;
            }

            return self::$enabledAnswer;
        }

        public static function requestId(): string
        {
            if (null !== self::$throw) {
                throw self::$throw;
            }

            return self::$requestId;
        }
    }
}

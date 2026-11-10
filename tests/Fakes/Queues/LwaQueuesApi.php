<?php

declare(strict_types=1);

namespace LWA\Queues;

// Deliberately NOT under tests/Fakes/*.php: bootstrap.php autoloads every file that matches
// that flat glob, and if LWA\Queues\Api always existed, Queue::available() could never be
// honestly exercised against a process where lwa_queues (and its Api class) never loaded at
// all. Required explicitly, only by the tests that need the API present: QueueTest (top of
// file, for the whole class) and any #[RunInSeparateProcess] test that wants the normal,
// version-1 double.
if (!class_exists(Api::class, false)) {
    /**
     * Test double for the producer API that lwa_queues ships in phase 2.
     */
    final class Api
    {
        public const API_VERSION = 1;

        /** @var list<array{module: string, handler: string, payload: array<string, mixed>, options: array<string, mixed>}> */
        public static array $dispatched = [];

        public static ?int $nextId = 42;

        public static ?\Throwable $throw = null;

        public static function reset(): void
        {
            self::$dispatched = [];
            self::$nextId = 42;
            self::$throw = null;
        }

        /**
         * @param array<string, mixed> $payload
         * @param array<string, mixed> $options
         */
        public static function dispatch(string $module, string $handler, array $payload = [], array $options = []): ?int
        {
            if (null !== self::$throw) {
                throw self::$throw;
            }
            self::$dispatched[] = ['module' => $module, 'handler' => $handler, 'payload' => $payload, 'options' => $options];

            return self::$nextId;
        }
    }
}

<?php

declare(strict_types=1);

namespace LWA\Queues;

// A stand-in for a future lwa_queues that moved its own API_VERSION past 1 but still
// declares, through supportsVersion(), that it remains compatible with modules built
// against version 1 — proving Queue prefers that declaration over the raw exact-match
// on API_VERSION (which alone would say "unavailable" here). Never required alongside
// another tests/Fakes/Queues/*.php fixture: both declare LWA\Queues\Api.
if (!class_exists(Api::class, false)) {
    final class Api
    {
        public const API_VERSION = 2;

        public static function supportsVersion(int $version): bool
        {
            return 1 === $version;
        }

        /**
         * @param array<string, mixed> $payload
         * @param array<string, mixed> $options
         */
        public static function dispatch(string $module, string $handler, array $payload = [], array $options = []): ?int
        {
            return 999;
        }
    }
}

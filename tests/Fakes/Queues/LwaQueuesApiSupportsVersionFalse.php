<?php

declare(strict_types=1);

namespace LWA\Queues;

// A stand-in for an lwa_queues that has dropped support for API version 1, even though
// its own API_VERSION constant would still exact-match — proving Queue consults
// supportsVersion() when it exists rather than falling back to the constant. Never
// required alongside another tests/Fakes/Queues/*.php fixture: both declare
// LWA\Queues\Api.
if (!class_exists(Api::class, false)) {
    final class Api
    {
        public const API_VERSION = 1;

        public static function supportsVersion(int $version): bool
        {
            return false;
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

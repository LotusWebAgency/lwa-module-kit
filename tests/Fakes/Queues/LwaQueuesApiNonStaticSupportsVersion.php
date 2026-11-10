<?php

declare(strict_types=1);

namespace LWA\Queues;

// A stand-in for a provider whose supportsVersion() is an INSTANCE method.
// method_exists() reports it all the same, and calling it statically raises an Error,
// so the kit must detect the non-static case and fall back to the exact-match check
// on API_VERSION (which says "incompatible" here, API_VERSION being 2).
// Never required alongside another tests/Fakes/Queues/*.php fixture: both declare LWA\Queues\Api.
if (!class_exists(Api::class, false)) {
    final class Api
    {
        public const API_VERSION = 2;

        public function supportsVersion(int $version): bool
        {
            return true;
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

<?php

declare(strict_types=1);

namespace LWA\Queues;

// A stand-in for a provider whose supportsVersion() throws.
// A plausible partial-deploy symptom if the real one touches a config table.
// available() must degrade to false rather than propagate into every hook on the page.
// Never required alongside another tests/Fakes/Queues/*.php fixture: both declare LWA\Queues\Api.
if (!class_exists(Api::class, false)) {
    final class Api
    {
        public const API_VERSION = 2;

        public static function supportsVersion(int $version): bool
        {
            throw new \RuntimeException('provider is half-deployed');
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

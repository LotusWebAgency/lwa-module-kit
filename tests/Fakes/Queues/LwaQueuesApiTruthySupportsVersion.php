<?php

declare(strict_types=1);

namespace LWA\Queues;

// A stand-in for a provider whose untyped supportsVersion() returns a non-empty string.
// A truthy cast would read that as "compatible" and call an API this kit was never
// built against, so the check uses strict identity against true.
// Never required alongside another tests/Fakes/Queues/*.php fixture: both declare LWA\Queues\Api.
if (!class_exists(Api::class, false)) {
    final class Api
    {
        public const API_VERSION = 7;

        public static function supportsVersion(int $version)
        {
            return 'no thanks';
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

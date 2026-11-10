<?php

declare(strict_types=1);

namespace LWA\Queues;

// A stand-in for an lwa_queues whose Api contract moved past API_VERSION 1 (or was rolled
// back below it) — used only by the isolated-process test asserting that Queue treats a
// version it was not built against as unavailable, never as compatible. Never required
// alongside tests/Fakes/Queues/LwaQueuesApi.php: both declare LWA\Queues\Api.
if (!class_exists(Api::class, false)) {
    final class Api
    {
        public const API_VERSION = 2;

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

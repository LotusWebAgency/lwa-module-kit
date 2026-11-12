<?php

declare(strict_types=1);

namespace LWA\Logger;

// A stand-in for an lwa_logger that has dropped support for API version 1 even though its
// API_VERSION constant would still match: proves Logger consults supportsVersion() when it
// exists. Never required alongside another tests/Fakes/Logger/*.php fixture.
if (!class_exists(Api::class, false)) {
    final class Api
    {
        public const API_VERSION = 1;

        public static function supportsVersion(int $v): bool
        {
            return false;
        }

        /**
         * @param array<string, mixed> $context
         * @param array<string, mixed> $options
         */
        public static function log(string $module, string $severity, string $event, string $message = '', array $context = [], array $options = []): void
        {
            throw new \LogicException('must not be called');
        }
    }
}

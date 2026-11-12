<?php

declare(strict_types=1);

namespace LWA\Logger;

// A stand-in for a provider whose supportsVersion() throws, a plausible partial-deploy
// symptom. Logger::available() must degrade to false rather than propagate.
// Never required alongside another tests/Fakes/Logger/*.php fixture.
if (!class_exists(Api::class, false)) {
    final class Api
    {
        public const API_VERSION = 2;

        public static function supportsVersion(int $v): bool
        {
            throw new \RuntimeException('provider is half-deployed');
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

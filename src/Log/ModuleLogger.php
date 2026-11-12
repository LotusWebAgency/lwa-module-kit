<?php

/**
 * Copyright since 2026 LW AGENCY LIMITED
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the MIT License
 * that is bundled with this package in the file LICENSE.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/MIT
 *
 * @author    LW AGENCY LIMITED <https://lotuswebagency.com>
 * @copyright Since 2026 LW AGENCY LIMITED
 * @license   https://opensource.org/licenses/MIT MIT License
 */

namespace LWA\Kit\Log;

use LWA\Kit\Internal\SafeLog;

/**
 * A logger bound to one module name, handed out by Logger::for(). Hand-rolled instead of
 * implementing Psr\Log\LoggerInterface: the signature carries an event name and an options
 * array, and the kit takes no PSR dependency.
 *
 * Nothing here throws. With lwa_logger available the call is forwarded untouched; when it
 * is not, or when it throws, warning and above are written to PrestaShopLogger and the
 * rest is dropped.
 */
final class ModuleLogger
{
    private const FALLBACK_SEVERITIES = [
        'warning' => 2,
        'error' => 3,
        'critical' => 4,
        'alert' => 4,
        'emergency' => 4,
    ];

    private const SEVERITY_ALIASES = [
        'warn' => 'warning',
        'err' => 'error',
        'crit' => 'critical',
        'emerg' => 'emergency',
        'fatal' => 'critical',
    ];

    private const FALLBACK_MAX_LENGTH = 1000;

    private static bool $loggedProviderFailure = false;

    public function __construct(private readonly string $module)
    {
    }

    /**
     * @param array<string, mixed> $context
     * @param array<string, mixed> $options
     */
    public function debug(string $event, string $message = '', array $context = [], array $options = []): void
    {
        $this->log('debug', $event, $message, $context, $options);
    }

    /**
     * @param array<string, mixed> $context
     * @param array<string, mixed> $options
     */
    public function info(string $event, string $message = '', array $context = [], array $options = []): void
    {
        $this->log('info', $event, $message, $context, $options);
    }

    /**
     * @param array<string, mixed> $context
     * @param array<string, mixed> $options
     */
    public function notice(string $event, string $message = '', array $context = [], array $options = []): void
    {
        $this->log('notice', $event, $message, $context, $options);
    }

    /**
     * @param array<string, mixed> $context
     * @param array<string, mixed> $options
     */
    public function warning(string $event, string $message = '', array $context = [], array $options = []): void
    {
        $this->log('warning', $event, $message, $context, $options);
    }

    /**
     * @param array<string, mixed> $context
     * @param array<string, mixed> $options
     */
    public function error(string $event, string $message = '', array $context = [], array $options = []): void
    {
        $this->log('error', $event, $message, $context, $options);
    }

    /**
     * @param array<string, mixed> $context
     * @param array<string, mixed> $options
     */
    public function critical(string $event, string $message = '', array $context = [], array $options = []): void
    {
        $this->log('critical', $event, $message, $context, $options);
    }

    /**
     * @param array<string, mixed> $context
     * @param array<string, mixed> $options
     */
    public function alert(string $event, string $message = '', array $context = [], array $options = []): void
    {
        $this->log('alert', $event, $message, $context, $options);
    }

    /**
     * @param array<string, mixed> $context
     * @param array<string, mixed> $options
     */
    public function emergency(string $event, string $message = '', array $context = [], array $options = []): void
    {
        $this->log('emergency', $event, $message, $context, $options);
    }

    /**
     * @param array<string, mixed> $context
     * @param array<string, mixed> $options passed through to LWA\Logger\Api::log()
     */
    public function log(string $severity, string $event, string $message = '', array $context = [], array $options = []): void
    {
        if (Logger::available()) {
            try {
                \LWA\Logger\Api::log($this->module, $severity, $event, $message, $context, $options);

                return;
            } catch (\Throwable $e) {
                if (!self::$loggedProviderFailure) {
                    self::$loggedProviderFailure = true;
                    SafeLog::error(sprintf('[%s] log provider call failed: %s', Logger::PROVIDER, $e->getMessage()));
                }
            }
        }

        $this->fallback($severity, $event, $message);
    }

    /**
     * Whether the provider would record this severity for this type. When lwa_logger is
     * unavailable or throws, it answers as the fallback would: true for warning and above.
     */
    public function enabled(string $severity, string $type = 'module'): bool
    {
        if (Logger::available()) {
            try {
                return true === \LWA\Logger\Api::enabled($severity, $type);
            } catch (\Throwable) {
                // Answered by the fallback below, the same as log().
            }
        }

        return null !== self::fallbackLevel($severity);
    }

    /**
     * The provider's id for the current request, to correlate lines; null when lwa_logger
     * is unavailable or throws.
     */
    public function requestId(): ?string
    {
        if (!Logger::available()) {
            return null;
        }

        try {
            $id = \LWA\Logger\Api::requestId();
        } catch (\Throwable) {
            return null;
        }

        return '' !== $id ? $id : null;
    }

    /**
     * @internal
     */
    public static function forget(): void
    {
        self::$loggedProviderFailure = false;
    }

    private function fallback(string $severity, string $event, string $message): void
    {
        $level = self::fallbackLevel($severity);
        if (null === $level) {
            return;
        }

        $line = sprintf('[%s] %s%s', $this->module, $event, '' !== $message ? ': ' . $message : '');

        try {
            if (class_exists(\PrestaShopLogger::class)) {
                \PrestaShopLogger::addLog(self::sanitize($line), $level, null, null, null, true);
            }
        } catch (\Throwable) {
            // Nowhere left to report to.
        }
    }

    /**
     * The PrestaShopLogger severity for a provider severity, or null when the fallback drops it.
     */
    private static function fallbackLevel(string $severity): ?int
    {
        $normalized = strtolower(trim($severity));
        $normalized = self::SEVERITY_ALIASES[$normalized] ?? $normalized;

        return self::FALLBACK_SEVERITIES[$normalized] ?? null;
    }

    /**
     * PrestaShopLogger::addLog() validates the message and rejects angle brackets and curly
     * braces, so they become parentheses; line breaks become spaces and other control
     * characters are dropped.
     */
    private static function sanitize(string $line): string
    {
        $line = str_replace(["\r\n", "\r", "\n"], ' ', $line);
        $line = preg_replace('/[\x00-\x1F\x7F]/', '', $line) ?? '';
        $line = strtr($line, ['<' => '(', '>' => ')', '{' => '(', '}' => ')']);

        return mb_substr($line, 0, self::FALLBACK_MAX_LENGTH, 'UTF-8');
    }
}

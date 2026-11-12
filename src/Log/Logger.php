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
 * Producer-side bridge to the lwa_logger module. Every call is safe when lwa_logger is
 * missing, disabled, broken or a version this kit was not built against: for(...)->x()
 * never throws, and warning and above fall back to PrestaShopLogger.
 *
 * LWA\Logger\Api is deliberately not part of this package and is left unscoped so every
 * module shares the same one at runtime; this class never type-hints against it and only
 * touches it behind a class_exists() guard, so it never autoload-fatals when it is absent.
 */
final class Logger
{
    public const PROVIDER = 'lwa_logger';

    /**
     * The API version this kit was built against. A provider that exposes a static
     * Api::supportsVersion() is asked directly whether it still speaks this version; a
     * provider without that method is held to an exact match on Api::API_VERSION. Same
     * contract as Queue\Queue::SUPPORTED_API_VERSION.
     */
    public const SUPPORTED_API_VERSION = 1;

    private static ?bool $available = null;

    private static ?string $unavailableReason = null;

    private static bool $loggedProviderProbeFailure = false;

    private static bool $loggedVersionMismatch = false;

    /**
     * Whether lwa_logger is installed, enabled, loadable and speaks SUPPORTED_API_VERSION.
     * Never throws. Memoized for the rest of the process, so a hot loop pays for the probe
     * once; a module enabled later in the same request is picked up on the next one.
     */
    public static function available(): bool
    {
        return self::$available ??= self::probe();
    }

    /**
     * Explains the most recent probe that came out false, when the cause was actionable: a
     * broken provider or an API version mismatch. Null for the ordinary steady states
     * (lwa_logger not installed, or installed but disabled) and whenever it is available.
     */
    public static function unavailableReason(): ?string
    {
        self::available();

        return self::$unavailableReason;
    }

    public static function for(string $module): ModuleLogger
    {
        return new ModuleLogger($module);
    }

    /**
     * Clears the memoized probe so a test can start from a clean process state.
     *
     * @internal
     */
    public static function forget(): void
    {
        self::$available = null;
        self::$unavailableReason = null;
        self::$loggedProviderProbeFailure = false;
        self::$loggedVersionMismatch = false;
        ModuleLogger::forget();
    }

    private static function probe(): bool
    {
        self::$unavailableReason = null;

        try {
            if (!class_exists(\Module::class) || !\Module::isEnabled(self::PROVIDER)) {
                return false;
            }

            // Instantiating the provider loads its autoloader, which defines LWA\Logger\Api.
            $provider = \Module::getInstanceByName(self::PROVIDER);

            if (!$provider instanceof \Module) {
                return false;
            }

            // Everything below is provider-controlled and must stay inside this try:
            // class_exists() runs the provider's autoloader, and isCompatibleVersion()
            // calls a static the provider itself declares.
            if (!class_exists(\LWA\Logger\Api::class)) {
                return false;
            }

            $compatible = self::isCompatibleVersion();
        } catch (\Throwable $e) {
            // A partial deploy makes isEnabled()/getInstanceByName() throw instead of
            // reporting "disabled". That must degrade like any other unavailable state.
            self::$unavailableReason = sprintf('%s failed to load: %s', self::PROVIDER, $e->getMessage());
            if (!self::$loggedProviderProbeFailure) {
                self::$loggedProviderProbeFailure = true;
                SafeLog::error(sprintf('[%s] log provider probe failed: %s', self::PROVIDER, $e->getMessage()));
            }

            return false;
        }

        if (!$compatible) {
            self::$unavailableReason = sprintf('%s speaks an API version this kit was not built against (kit requires API %d)', self::PROVIDER, self::SUPPORTED_API_VERSION);
            if (!self::$loggedVersionMismatch) {
                self::$loggedVersionMismatch = true;
                SafeLog::error(sprintf('[%s] %s', self::PROVIDER, self::$unavailableReason));
            }

            return false;
        }

        return true;
    }

    private static function isCompatibleVersion(): bool
    {
        $apiClass = self::apiClassName();

        if (self::hasStaticSupportsVersion($apiClass)) {
            // Strict identity, not a truthy cast: see Queue::isCompatibleVersion().
            return true === $apiClass::supportsVersion(self::SUPPORTED_API_VERSION);
        }

        return self::SUPPORTED_API_VERSION === self::apiVersion();
    }

    /**
     * method_exists() alone also reports instance methods, and calling one statically
     * raises an Error. Reflection settles it before anything is called.
     */
    private static function hasStaticSupportsVersion(string $apiClass): bool
    {
        if (!method_exists($apiClass, 'supportsVersion')) {
            return false;
        }

        try {
            return (new \ReflectionMethod($apiClass, 'supportsVersion'))->isStatic();
        } catch (\ReflectionException) {
            return false;
        }
    }

    /**
     * Read through a plain `string` boundary so PHPStan, which only sees the stub in
     * stubs/prestashop.php, cannot fold the runtime method_exists() check away.
     */
    private static function apiClassName(): string
    {
        return \LWA\Logger\Api::class;
    }

    /**
     * Read through a plain `int` boundary for the same reason as apiClassName().
     */
    private static function apiVersion(): int
    {
        return \LWA\Logger\Api::API_VERSION;
    }
}

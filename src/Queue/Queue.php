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

declare(strict_types=1);

namespace LWA\Kit\Queue;

use LWA\Kit\Internal\HandlerName;
use LWA\Kit\Internal\SafeLog;

/**
 * Producer-side bridge to the lwa_queues module. Every call is safe when lwa_queues is
 * missing, disabled, broken or a version this kit was not built against: dispatch()
 * returns null and dispatchOrRun() executes the handler inline.
 *
 * LWA\Queues\Api is deliberately not part of this package and is left unscoped so every
 * module shares the same one at runtime; this class never type-hints against it and only
 * touches it behind a class_exists() guard, so it never autoload-fatals when it is absent.
 */
final class Queue
{
    public const PROVIDER = 'lwa_queues';

    /**
     * The API version this kit was built against. A provider that exposes a static
     * Api::supportsVersion() is asked directly whether it still speaks this version, so a
     * later lwa_queues can stay compatible with modules built against an older API; a
     * provider without that method (today's lwa_queues, and any that predates it) is held
     * to an exact match on Api::API_VERSION instead, older or newer alike. See
     * isCompatibleVersion().
     */
    public const SUPPORTED_API_VERSION = 1;

    private static ?string $unavailableReason = null;

    private static bool $loggedProviderProbeFailure = false;

    private static bool $loggedVersionMismatch = false;

    /**
     * Whether lwa_queues is installed, enabled, loadable and speaks SUPPORTED_API_VERSION.
     * Never throws. Records why not for unavailableReason().
     */
    public static function available(): bool
    {
        self::$unavailableReason = null;

        try {
            if (!class_exists(\Module::class) || !\Module::isEnabled(self::PROVIDER)) {
                return false;
            }

            // Instantiating the provider loads its autoloader, which defines LWA\Queues\Api.
            $provider = \Module::getInstanceByName(self::PROVIDER);

            if (!$provider instanceof \Module) {
                return false;
            }

            // Everything below is provider-controlled and must stay inside this try:
            // class_exists() runs the provider's autoloader, and isCompatibleVersion()
            // calls a static the provider itself declares.
            if (!class_exists(\LWA\Queues\Api::class)) {
                return false;
            }

            $compatible = self::isCompatibleVersion();
        } catch (\Throwable $e) {
            // A partial deploy (e.g. the provider's scoped vendor/autoload.php missing)
            // makes isEnabled()/getInstanceByName() throw instead of reporting "disabled".
            // That must degrade like any other unavailable state, not 500 every hook in
            // every lwa_* module on the page.
            self::$unavailableReason = sprintf('%s failed to load: %s', self::PROVIDER, $e->getMessage());
            if (!self::$loggedProviderProbeFailure) {
                self::$loggedProviderProbeFailure = true;
                SafeLog::error(sprintf('[%s] queue provider probe failed: %s', self::PROVIDER, $e->getMessage()));
            }

            return false;
        }

        if (!$compatible) {
            self::$unavailableReason = sprintf('%s speaks an API version this kit was not built against (kit requires API %d)', self::PROVIDER, self::SUPPORTED_API_VERSION);
            // A provider that is present but incompatible is a misconfiguration, unlike
            // the steady state "not installed": worth one log line per request.
            if (!self::$loggedVersionMismatch) {
                self::$loggedVersionMismatch = true;
                SafeLog::error(sprintf('[%s] %s', self::PROVIDER, self::$unavailableReason));
            }

            return false;
        }

        return true;
    }

    /**
     * Explains the most recent available() === false, when the cause was actionable: a
     * broken provider (see the catch above) or an API version mismatch. Null for the
     * ordinary steady states — lwa_queues not installed, or installed but disabled — and
     * whenever the queue is available. Meant for a module's BO page or health check, not
     * for control flow.
     */
    public static function unavailableReason(): ?string
    {
        return self::$unavailableReason;
    }

    /**
     * Prefers asking the provider directly whether it still supports
     * SUPPORTED_API_VERSION; falls back to an exact match on Api::API_VERSION for a
     * provider that predates supportsVersion() and cannot answer that question.
     */
    private static function isCompatibleVersion(): bool
    {
        $apiClass = self::apiClassName();

        if (self::hasStaticSupportsVersion($apiClass)) {
            // Strict identity, not a truthy cast: an untyped provider returning a
            // non-empty string would otherwise read as "compatible" and we would call
            // an API this kit was never built against.
            return true === $apiClass::supportsVersion(self::SUPPORTED_API_VERSION);
        }

        return self::SUPPORTED_API_VERSION === self::apiVersion();
    }

    /**
     * method_exists() alone is not enough: it also reports instance methods, and calling
     * one statically raises an Error. Reflection settles it before we call anything.
     */
    private static function hasStaticSupportsVersion(string $apiClass): bool
    {
        if (!method_exists($apiClass, 'supportsVersion')) {
            return false;
        }

        try {
            return (new \ReflectionMethod($apiClass, 'supportsVersion'))->isStatic();
        } catch (\ReflectionException $e) {
            return false;
        }
    }

    /**
     * Reads Api::class through a boundary with a plain `string` return type, not the
     * class-string literal PHPStan would otherwise see straight off `::class`: the real
     * lwa_queues can ship a supportsVersion() method or not, and method_exists() above
     * must stay a genuine runtime check rather than something static analysis, reflecting
     * only the phpstan-only stub in stubs/prestashop.php, could fold away as always
     * true or always false.
     */
    private static function apiClassName(): string
    {
        return \LWA\Queues\Api::class;
    }

    /**
     * Reads Api::API_VERSION through a boundary with a plain `int` return type, not the
     * literal PHPStan would otherwise see straight off the constant: the real lwa_queues
     * can ship any int here, and the check above must stay a genuine runtime comparison
     * rather than something static analysis, looking only at this package, could fold
     * away as always true.
     */
    private static function apiVersion(): int
    {
        return \LWA\Queues\Api::API_VERSION;
    }

    /**
     * Never throws. Null does not say why the job was not queued, so it is not a
     * license to run the job yourself: dispatchOrRun() makes that distinction.
     *
     * @param string $module the dispatching module's technical name
     * @param string $handler a key of that module's lwaQueueHandlers() map
     * @param array<string, mixed> $payload scalars and arrays only; it is stored as JSON
     * @param array<string, mixed> $options passed through to LWA\Queues\Api::dispatch()
     *
     * @return int|null the job id; null when lwa_queues is unavailable, refused the job
     *                  (oversize payload, invalid module name) or threw (logged)
     */
    public static function dispatch(string $module, string $handler, array $payload = [], array $options = []): ?int
    {
        if (!self::available()) {
            return null;
        }

        try {
            return \LWA\Queues\Api::dispatch($module, $handler, $payload, $options);
        } catch (\Throwable $e) {
            SafeLog::error(sprintf('[%s] queue dispatch of "%s" failed: %s', HandlerName::sanitize($module), HandlerName::sanitize($handler), $e->getMessage()));

            return null;
        }
    }

    /**
     * Queues the job when lwa_queues is available, otherwise runs it now through the
     * module's lwaQueueHandle() (see HandlesQueueJobs).
     *
     * The inline fallback is gated on available(), not on dispatch() returning null. A
     * queue that was available and still returned null either refused the job or failed
     * after it may have stored it, and the bridge cannot tell which; running the job
     * inline could execute it twice. That case is logged and returned as null for the
     * caller to retry or surface.
     *
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $options
     *
     * @return int|null the job id when queued; null when it ran inline, or when the queue
     *                  was available but did not take the job (the job was not performed)
     *
     * @throws \LogicException when the job must run inline and $module has no lwaQueueHandle()
     * @throws \Throwable whatever an inline job throws; it is never swallowed
     */
    public static function dispatchOrRun(\Module $module, string $handler, array $payload = [], array $options = []): ?int
    {
        $wasAvailable = self::available();

        $jobId = self::dispatch($module->name, $handler, $payload, $options);
        if (null !== $jobId) {
            return $jobId;
        }

        if ($wasAvailable) {
            SafeLog::error(sprintf('[%s] queue declined "%s"; not running it inline to avoid double execution — the job was NOT performed', $module->name, HandlerName::sanitize($handler)));

            return null;
        }

        if (!method_exists($module, 'lwaQueueHandle')) {
            throw new \LogicException(sprintf('Module %s cannot run "%s" inline: it has no lwaQueueHandle() method (use the HandlesQueueJobs trait)', $module->name, HandlerName::sanitize($handler)));
        }

        $module->lwaQueueHandle($handler, $payload, new InlineJobContext($handler));

        return null;
    }
}

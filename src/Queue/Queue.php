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

/**
 * Producer-side bridge to the lwa_queues module. Every call is safe when lwa_queues is
 * missing, disabled, absent, broken or a version this kit was not built against: dispatch()
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
            self::logProviderProbeFailure($e);

            return false;
        }

        if (!$compatible) {
            self::$unavailableReason = sprintf('%s speaks an API version this kit was not built against (kit requires API %d)', self::PROVIDER, self::SUPPORTED_API_VERSION);
            self::logVersionMismatch();

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

    private static function logProviderProbeFailure(\Throwable $e): void
    {
        if (self::$loggedProviderProbeFailure) {
            return;
        }

        self::$loggedProviderProbeFailure = true;

        if (class_exists(\PrestaShopLogger::class)) {
            \PrestaShopLogger::addLog(sprintf('[%s] queue provider probe failed: %s', self::PROVIDER, $e->getMessage()), 3);
        }
    }

    /**
     * A provider that IS present but speaks a version this kit does not support is a
     * misconfiguration, not a steady state like "lwa_queues isn't installed" — so, unlike
     * that ordinary case, this is worth one log line per request rather than none.
     */
    private static function logVersionMismatch(): void
    {
        if (self::$loggedVersionMismatch) {
            return;
        }

        self::$loggedVersionMismatch = true;

        if (class_exists(\PrestaShopLogger::class)) {
            \PrestaShopLogger::addLog(sprintf('[%s] %s', self::PROVIDER, self::$unavailableReason ?? 'API version mismatch'), 3);
        }
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
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $options
     *
     * @return int|null the job id on success; null when lwa_queues is unavailable, when the
     *                  queue refused the job, or when dispatching threw. The provider
     *                  contract this kit targets is Api::dispatch(): ?int — per the design
     *                  spec it returns null when it declines the job (oversize payload,
     *                  invalid module name) and throws on database errors — so null from
     *                  THIS method always means "the queue did not take this job", never
     *                  "it took it and gave no id". A caller must not treat that null as
     *                  license to run the job inline unless available() is also false; see
     *                  dispatchOrRun(), which makes exactly that distinction.
     */
    public static function dispatch(string $module, string $handler, array $payload = [], array $options = []): ?int
    {
        if (!self::available()) {
            return null;
        }

        try {
            return \LWA\Queues\Api::dispatch($module, $handler, $payload, $options);
        } catch (\Throwable $e) {
            if (class_exists(\PrestaShopLogger::class)) {
                \PrestaShopLogger::addLog(sprintf('[%s] queue dispatch of "%s" failed: %s', $module, self::sanitizeHandler($handler), $e->getMessage()), 3);
            }

            return null;
        }
    }

    /**
     * Queues the job when lwa_queues is available, otherwise runs it now through the
     * module's lwaQueueHandle() (see HandlesQueueJobs). A failure from an inline run is
     * never swallowed: the job's exception (or InlineJobContext::fail()'s) propagates
     * to the caller exactly as it would from any other synchronous call.
     *
     * Falling back to inline execution is gated on available(), not on dispatch()'s
     * return value: a queue that WAS available but still handed back null (a refusal —
     * oversize payload, invalid module name, a future dedupe or rate limit — or a caught
     * dispatch failure) is never run inline here, because the bridge cannot tell a clean
     * refusal from a failure that may already have persisted the job, and running it again
     * would double-execute it. That null is never silent: it is written to
     * PrestaShopLogger at severity 3 naming the module and handler, and returned to the
     * caller, whose job it is to retry or surface it. Only genuine unavailability (no
     * lwa_queues installed, disabled, or an incompatible API_VERSION) runs the job inline.
     *
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $options
     *
     * @return int|null the job id when queued, null when it ran inline or when the queue
     *                  was available but declined the job
     */
    public static function dispatchOrRun(\Module $module, string $handler, array $payload = [], array $options = []): ?int
    {
        $wasAvailable = self::available();

        $jobId = self::dispatch($module->name, $handler, $payload, $options);
        if (null !== $jobId) {
            return $jobId;
        }

        if ($wasAvailable) {
            if (class_exists(\PrestaShopLogger::class)) {
                \PrestaShopLogger::addLog(sprintf('[%s] queue declined "%s"; not running it inline to avoid double execution — the job was NOT performed', $module->name, self::sanitizeHandler($handler)), 3);
            }

            return null;
        }

        if (!method_exists($module, 'lwaQueueHandle')) {
            throw new \LogicException(sprintf('Module %s cannot run "%s" inline: it has no lwaQueueHandle() method (use the HandlesQueueJobs trait)', $module->name, self::sanitizeHandler($handler)));
        }

        $module->lwaQueueHandle($handler, $payload, new InlineJobContext($handler));

        return null;
    }

    /**
     * $handler crosses a module boundary (another module's dispatch() call, or lwa_queues
     * itself) and lands verbatim in log and exception text. Strip control characters and
     * cap the length so it cannot forge extra-looking log lines or bloat a message.
     */
    private static function sanitizeHandler(string $handler): string
    {
        // U+0085, U+2028 and U+2029 are line separators too, so they forge log lines
        // just as well as \n. On invalid UTF-8 the /u pattern refuses to run at all,
        // and then ASCII-only is the safe answer rather than passing the value through.
        $clean = preg_replace('/[\x00-\x1F\x7F\x{0085}\x{2028}\x{2029}]/u', '', $handler);

        if (null === $clean) {
            $clean = preg_replace('/[^\x20-\x7E]/', '', $handler) ?? '';
        }

        // Cut on character boundaries: a byte-wise substr can split a codepoint and hand
        // PrestaShopLogger invalid utf8mb4, which MySQL then rejects.
        return mb_strlen($clean, 'UTF-8') > 64 ? mb_substr($clean, 0, 64, 'UTF-8') . '…' : $clean;
    }
}

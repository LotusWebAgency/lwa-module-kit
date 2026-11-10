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
 * Gives a module main class the lwaQueueHandle() callback that lwa_queues (and the
 * inline fallback) invoke. The module maps handler names to Job classes.
 */
trait HandlesQueueJobs
{
    /**
     * @return array<string, class-string<Job>>
     */
    abstract protected function lwaQueueHandlers(): array;

    /**
     * $handler and $payload arrive from another module (lwa_queues, or a caller of
     * dispatchOrRun()): never instantiate a class named directly from that input, only
     * from the fixed lwaQueueHandlers() map this module itself controls.
     *
     * @param array<string, mixed> $payload
     */
    public function lwaQueueHandle(string $handler, array $payload, object $context): void
    {
        $handlers = $this->lwaQueueHandlers();
        if (!isset($handlers[$handler])) {
            throw new \InvalidArgumentException(sprintf('Unknown queue handler "%s"', self::sanitizeHandler($handler)));
        }

        $class = $handlers[$handler];
        if (!is_subclass_of($class, Job::class)) {
            throw new \LogicException(sprintf('Handler "%s" maps to %s, which does not implement %s', self::sanitizeHandler($handler), $class, Job::class));
        }

        (new $class($payload))->handle($context);
    }

    /**
     * $handler arrives from another module (lwa_queues, or a caller of dispatchOrRun())
     * and lands verbatim in exception text. Strip control characters and cap the length
     * so it cannot forge extra-looking log lines or bloat a message.
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

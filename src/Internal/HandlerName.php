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

namespace LWA\Kit\Internal;

/**
 * A queue handler name crosses a module boundary (another module's dispatch() call, or
 * lwa_queues itself) and lands in log lines and exception messages.
 *
 * @internal
 */
final class HandlerName
{
    private const MAX_LENGTH = 64;

    /**
     * Strips control characters and caps the length, so the name cannot forge log lines
     * or bloat a message.
     */
    public static function sanitize(string $handler): string
    {
        // U+0085, U+2028 and U+2029 are line separators too. On invalid UTF-8 the /u
        // pattern refuses to run, and then ASCII-only is the safe answer.
        $clean = preg_replace('/[\x00-\x1F\x7F\x{0085}\x{2028}\x{2029}]/u', '', $handler)
            ?? preg_replace('/[^\x20-\x7E]/', '', $handler)
            ?? '';

        // Cut on character boundaries: a byte-wise substr can split a codepoint and hand
        // PrestaShopLogger invalid utf8mb4, which MySQL rejects.
        return mb_strlen($clean, 'UTF-8') > self::MAX_LENGTH
            ? mb_substr($clean, 0, self::MAX_LENGTH, 'UTF-8') . '…'
            : $clean;
    }
}

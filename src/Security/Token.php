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

namespace LWA\Kit\Security;

/**
 * Shared-secret tokens for cron URLs, front controllers and AJAX endpoints.
 */
final class Token
{
    private const MIN_BYTES = 16;

    /**
     * @param int $bytes amount of randomness; the result is twice as many hex characters
     *
     * @throws \InvalidArgumentException below 16 bytes
     */
    public static function generate(int $bytes = 24): string
    {
        if ($bytes < self::MIN_BYTES) {
            throw new \InvalidArgumentException(sprintf('A token needs at least %d random bytes', self::MIN_BYTES));
        }

        return bin2hex(random_bytes($bytes));
    }

    /**
     * Constant-time comparison. An empty expected token never matches.
     */
    public static function equals(
        #[\SensitiveParameter] string $expected,
        #[\SensitiveParameter] string $provided,
    ): bool {
        if ('' === $expected) {
            return false;
        }

        return hash_equals($expected, $provided);
    }
}

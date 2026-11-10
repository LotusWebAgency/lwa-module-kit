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

final class Token
{
    public static function generate(int $bytes = 24): string
    {
        if ($bytes < 16) {
            throw new \InvalidArgumentException('A token needs at least 16 random bytes');
        }

        return bin2hex(random_bytes($bytes));
    }

    public static function equals(string $expected, string $provided): bool
    {
        if ('' === $expected) {
            return false;
        }

        return hash_equals($expected, $provided);
    }
}

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
 * Reads a PrestaShop bootstrap constant (_PS_MODULE_DIR_, _DB_PREFIX_, ...) as a string.
 * The names are resolved at runtime because php-scoper must leave them unprefixed.
 *
 * @internal
 */
final class PsConstant
{
    /**
     * @throws \LogicException when PrestaShop has not defined the constant as a string
     */
    public static function string(string $name): string
    {
        $value = defined($name) ? constant($name) : null;
        if (!is_string($value)) {
            throw new \LogicException(sprintf('PrestaShop constant %s is not defined as a string', $name));
        }

        return $value;
    }
}

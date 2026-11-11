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

// phpstan-only declarations. Not loaded at runtime, not loaded in tests.

namespace {
    class Module
    {
        public string $name = '';

        public static function isEnabled(string $moduleName): bool
        {
            return false;
        }

        /** @return Module|false */
        public static function getInstanceByName(string $moduleName)
        {
            return false;
        }

        /** @param string|array<int, string> $hookName */
        public function registerHook($hookName): bool
        {
            return false;
        }
    }

    class Configuration
    {
        /** @return string|false */
        public static function get(string $key, ?int $idLang = null, ?int $idShopGroup = null, ?int $idShop = null, $default = false)
        {
            return false;
        }

        public static function hasKey(string $key, ?int $idLang = null, ?int $idShopGroup = null, ?int $idShop = null): bool
        {
            return false;
        }

        /** @param mixed $values */
        public static function updateValue(string $key, $values, bool $html = false, ?int $idShopGroup = null, ?int $idShop = null): bool
        {
            return false;
        }

        /** @param mixed $values */
        public static function updateGlobalValue(string $key, $values, bool $html = false): bool
        {
            return false;
        }

        public static function deleteByName(string $key): bool
        {
            return false;
        }
    }

    class Db
    {
        public static function getInstance(bool $master = true): Db
        {
            return new self();
        }

        public function execute(string $sql, bool $useCache = true): bool
        {
            return false;
        }
    }

    class PrestaShopLogger
    {
        public static function addLog(string $message, int $severity = 1, ?int $errorCode = null, ?string $objectType = null, ?int $objectId = null, bool $allowDuplicate = false, ?int $idEmployee = null): bool
        {
            return false;
        }
    }
}

namespace PrestaShopBundle\Controller\Admin {
    abstract class FrameworkBundleAdminController
    {
    }

    abstract class PrestaShopAdminController
    {
    }
}

/*
 * Referenced only by AdminControllerBase's @method tags. They exist so this repo can analyse
 * its own source; a consuming module analyses against a real PrestaShop tree, which brings the
 * genuine classes. Nothing outside this repo's phpstan.neon ever scans this file, so these
 * cannot collide with the real Symfony or PrestaShop classes.
 */

namespace Symfony\Component\HttpFoundation {
    class Response
    {
    }

    class RedirectResponse extends Response
    {
    }
}

namespace Symfony\Component\Form {
    interface FormInterface
    {
    }
}

namespace PrestaShop\PrestaShop\Core\Grid {
    interface GridInterface
    {
    }
}

namespace LWA\Queues {
    final class Api
    {
        public const API_VERSION = 1;

        /** @param array<string, mixed> $payload @param array<string, mixed> $options */
        public static function dispatch(string $module, string $handler, array $payload = [], array $options = []): ?int
        {
            return null;
        }
    }
}

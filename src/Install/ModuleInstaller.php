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

namespace LWA\Kit\Install;

final class ModuleInstaller
{
    public function __construct(
        private \Module $module,
        private string $modulePath,
        private string $dbPrefix,
        private string $engine,
    ) {
    }

    public static function forModule(\Module $module): self
    {
        return new self(
            $module,
            rtrim((string) constant('_PS_MODULE_DIR_'), '/') . '/' . $module->name,
            (string) constant('_DB_PREFIX_'),
            (string) constant('_MYSQL_ENGINE_')
        );
    }

    public function runSqlFile(string $relativePath): bool
    {
        $db = \Db::getInstance();
        foreach (SqlScript::load($this->modulePath . '/' . ltrim($relativePath, '/'), $this->dbPrefix, $this->engine) as $statement) {
            if (!$db->execute($statement)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param list<string> $hooks
     */
    public function registerHooks(array $hooks): bool
    {
        foreach ($hooks as $hook) {
            if (!$this->module->registerHook($hook)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Writes defaults for keys that do not exist yet; existing values survive reinstalls and upgrades.
     *
     * @param array<string, scalar> $defaults
     */
    public function seedConfiguration(array $defaults): bool
    {
        foreach ($defaults as $key => $value) {
            if (\Configuration::hasKey($key)) {
                continue;
            }
            if (!\Configuration::updateValue($key, $value)) {
                return false;
            }
        }

        return true;
    }
}

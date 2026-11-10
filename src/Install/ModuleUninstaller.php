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

final class ModuleUninstaller
{
    public function __construct(
        private \Module $module,
        private string $modulePath,
        private string $dbPrefix,
        private string $engine,
    ) {
    }

    /**
     * Not used by run(): uninstalling touches only SQL and Configuration keys, and
     * PrestaShop itself removes the module's hooks and tabs. Kept for callers that
     * want the module instance back after uninstalling, and so the constructor
     * mirrors ModuleInstaller's.
     */
    public function module(): \Module
    {
        return $this->module;
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

    /**
     * With $keepData the tables and the configuration stay; PrestaShop still removes hooks and tabs itself.
     *
     * @param list<string> $configKeys
     */
    public function run(string $sqlRelativePath, array $configKeys, bool $keepData): bool
    {
        if ($keepData) {
            return true;
        }

        $db = \Db::getInstance();
        foreach (SqlScript::load($this->modulePath . '/' . ltrim($sqlRelativePath, '/'), $this->dbPrefix, $this->engine) as $statement) {
            if (!$db->execute($statement)) {
                return false;
            }
        }

        foreach ($configKeys as $key) {
            \Configuration::deleteByName($key);
        }

        return true;
    }
}

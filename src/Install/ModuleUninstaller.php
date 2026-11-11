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

/**
 * Removes a module's tables and configuration in uninstall(). PrestaShop removes the
 * hooks and tabs itself. A module that keeps its data on uninstall does not call this:
 *
 *   return parent::uninstall()
 *       && ($keepData || ModuleUninstaller::forModule($this)->run('sql/uninstall.sql', Settings::keys()));
 */
final class ModuleUninstaller
{
    public function __construct(private SqlRunner $sql)
    {
    }

    /**
     * @throws \LogicException outside a booted PrestaShop
     */
    public static function forModule(\Module $module): self
    {
        return new self(SqlRunner::forModule($module));
    }

    /**
     * Runs the SQL script, then deletes the configuration keys in every shop scope. The
     * keys are left alone when the script fails, so a retry still finds the settings.
     *
     * @param string $sqlRelativePath path inside the module directory, e.g. "sql/uninstall.sql"
     * @param list<string> $configKeys
     *
     * @return bool false when a statement failed
     *
     * @throws \RuntimeException when the file is unreadable or holds no statement
     */
    public function run(string $sqlRelativePath, array $configKeys): bool
    {
        if (!$this->sql->run($sqlRelativePath)) {
            return false;
        }

        foreach ($configKeys as $key) {
            \Configuration::deleteByName($key);
        }

        return true;
    }
}

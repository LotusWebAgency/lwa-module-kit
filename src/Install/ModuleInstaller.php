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
 * The steps of a module's install(): SQL script, hooks, configuration defaults. Each
 * returns false on failure so install() can chain them with &&.
 *
 *   $installer = ModuleInstaller::forModule($this);
 *   return parent::install()
 *       && $installer->runSqlFile('sql/install.sql')
 *       && $installer->registerHooks(['actionCronJob'])
 *       && $installer->seedConfiguration(['LWA_FOO_ENABLED' => 1]);
 */
final class ModuleInstaller
{
    public function __construct(private \Module $module, private SqlRunner $sql)
    {
    }

    /**
     * @throws \LogicException outside a booted PrestaShop
     */
    public static function forModule(\Module $module): self
    {
        return new self($module, SqlRunner::forModule($module));
    }

    /**
     * @param string $relativePath path inside the module directory, e.g. "sql/install.sql"
     *
     * @return bool false as soon as a statement fails; earlier statements stay applied
     *
     * @throws \RuntimeException when the file is unreadable or holds no statement
     */
    public function runSqlFile(string $relativePath): bool
    {
        return $this->sql->run($relativePath);
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
     * Writes shop-wide defaults for keys that have no global value yet; existing values
     * survive reinstalls and upgrades. Both the check and the write use the global scope,
     * whatever shop context the install runs in, so a per-shop value a merchant set in
     * multistore is never touched.
     *
     * @param array<string, scalar> $defaults
     */
    public function seedConfiguration(array $defaults): bool
    {
        foreach ($defaults as $key => $value) {
            if (\Configuration::hasKey($key)) {
                continue;
            }
            if (!\Configuration::updateGlobalValue($key, $value)) {
                return false;
            }
        }

        return true;
    }
}

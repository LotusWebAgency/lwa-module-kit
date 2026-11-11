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

use LWA\Kit\Internal\PsConstant;

/**
 * Executes a module's SQL script, statement by statement, against the shop database.
 *
 * @internal shared by ModuleInstaller and ModuleUninstaller
 */
final class SqlRunner
{
    public function __construct(
        private string $modulePath,
        private string $dbPrefix,
        private string $engine,
    ) {
    }

    public static function forModule(\Module $module): self
    {
        return new self(
            rtrim(PsConstant::string('_PS_MODULE_DIR_'), '/') . '/' . $module->name,
            PsConstant::string('_DB_PREFIX_'),
            PsConstant::string('_MYSQL_ENGINE_')
        );
    }

    /**
     * Stops at the first failing statement. DDL is not transactional in MySQL, so the
     * statements already executed stay applied; scripts must be re-runnable
     * (CREATE TABLE IF NOT EXISTS, DROP TABLE IF EXISTS).
     *
     * @param string $relativePath path inside the module directory, e.g. "sql/install.sql"
     *
     * @return bool false as soon as a statement fails
     *
     * @throws \RuntimeException when the file is unreadable or holds no statement
     */
    public function run(string $relativePath): bool
    {
        $db = \Db::getInstance();
        foreach (SqlScript::load($this->modulePath . '/' . ltrim($relativePath, '/'), $this->dbPrefix, $this->engine) as $statement) {
            if (!$db->execute($statement)) {
                return false;
            }
        }

        return true;
    }
}

<?php

declare(strict_types=1);

namespace LWA\Kit\Tests\Unit\Install;

use LWA\Kit\Install\ModuleUninstaller;
use LWA\Kit\Install\SqlRunner;
use PHPUnit\Framework\TestCase;

final class ModuleUninstallerTest extends TestCase
{
    private ModuleUninstaller $uninstaller;

    protected function setUp(): void
    {
        \Db::reset();
        \Configuration::reset();
        \Configuration::updateGlobalValue('LWA_FIXTURE_ENABLED', '1');
        \Configuration::updateValue('LWA_FIXTURE_TOKEN', 'abc');
        $this->uninstaller = new ModuleUninstaller(new SqlRunner(__DIR__ . '/../../Fixtures', 'ps_', 'InnoDB'));
    }

    public function testDropsTablesAndDeletesConfiguration(): void
    {
        self::assertTrue($this->uninstaller->run('sql/uninstall.sql', ['LWA_FIXTURE_ENABLED', 'LWA_FIXTURE_TOKEN']));

        self::assertSame(['DROP TABLE IF EXISTS `ps_lwa_fixture_item`'], \Db::$executed);
        self::assertFalse(\Configuration::get('LWA_FIXTURE_ENABLED'));
        self::assertFalse(\Configuration::get('LWA_FIXTURE_TOKEN'));
    }

    public function testASqlFailureLeavesTheConfigurationInPlace(): void
    {
        \Db::$failOn = ['DROP TABLE'];

        self::assertFalse($this->uninstaller->run('sql/uninstall.sql', ['LWA_FIXTURE_ENABLED']));
        self::assertSame('1', \Configuration::get('LWA_FIXTURE_ENABLED'));
    }

    public function testForModuleReadsTheStorePathAndPrefixFromPrestaShopConstants(): void
    {
        self::defineModuleConstantsOnce();

        $module = new \Module();
        $module->name = 'Fixtures';

        $uninstaller = ModuleUninstaller::forModule($module);

        self::assertTrue($uninstaller->run('sql/uninstall.sql', []));
        self::assertSame(['DROP TABLE IF EXISTS `ps_lwa_fixture_item`'], \Db::$executed);
    }

    /**
     * Defines the PrestaShop constants forModule() reads, once per process, pointing
     * _PS_MODULE_DIR_ at tests/ so a Module named "Fixtures" resolves to tests/Fixtures.
     */
    private static function defineModuleConstantsOnce(): void
    {
        if (!defined('_PS_MODULE_DIR_')) {
            define('_PS_MODULE_DIR_', __DIR__ . '/../..');
        }
        if (!defined('_DB_PREFIX_')) {
            define('_DB_PREFIX_', 'ps_');
        }
        if (!defined('_MYSQL_ENGINE_')) {
            define('_MYSQL_ENGINE_', 'InnoDB');
        }
    }
}

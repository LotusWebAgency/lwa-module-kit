<?php

declare(strict_types=1);

namespace LWA\Kit\Tests\Unit\Install;

use LWA\Kit\Install\ModuleUninstaller;
use PHPUnit\Framework\TestCase;

final class ModuleUninstallerTest extends TestCase
{
    private ModuleUninstaller $uninstaller;

    protected function setUp(): void
    {
        \Db::reset();
        \Configuration::reset();
        \Configuration::updateValue('LWA_FIXTURE_ENABLED', '1');
        \Configuration::updateValue('LWA_FIXTURE_TOKEN', 'abc');
        $module = new \Module();
        $module->name = 'lwa_fixture';
        $this->uninstaller = new ModuleUninstaller($module, __DIR__ . '/../../Fixtures', 'ps_', 'InnoDB');
    }

    public function testDropsTablesAndDeletesConfiguration(): void
    {
        self::assertTrue($this->uninstaller->run('sql/uninstall.sql', ['LWA_FIXTURE_ENABLED', 'LWA_FIXTURE_TOKEN'], false));

        self::assertSame(['DROP TABLE IF EXISTS `ps_lwa_fixture_item`'], \Db::$executed);
        self::assertFalse(\Configuration::hasKey('LWA_FIXTURE_ENABLED'));
        self::assertFalse(\Configuration::hasKey('LWA_FIXTURE_TOKEN'));
    }

    public function testKeepDataLeavesEverythingInPlace(): void
    {
        self::assertTrue($this->uninstaller->run('sql/uninstall.sql', ['LWA_FIXTURE_ENABLED'], true));

        self::assertSame([], \Db::$executed);
        self::assertTrue(\Configuration::hasKey('LWA_FIXTURE_ENABLED'));
    }

    public function testReportsSqlFailure(): void
    {
        \Db::$failOn = ['DROP TABLE'];

        self::assertFalse($this->uninstaller->run('sql/uninstall.sql', [], false));
    }

    public function testForModuleReadsTheStorePathAndPrefixFromPrestaShopConstants(): void
    {
        self::defineModuleConstantsOnce();

        $module = new \Module();
        $module->name = 'Fixtures';

        $uninstaller = ModuleUninstaller::forModule($module);

        self::assertTrue($uninstaller->run('sql/uninstall.sql', [], false));
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

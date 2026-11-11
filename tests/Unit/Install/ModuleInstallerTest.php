<?php

declare(strict_types=1);

namespace LWA\Kit\Tests\Unit\Install;

use LWA\Kit\Install\ModuleInstaller;
use LWA\Kit\Install\SqlRunner;
use LWA\Kit\Internal\PsConstant;
use PHPUnit\Framework\TestCase;

final class ModuleInstallerTest extends TestCase
{
    private \Module $module;
    private ModuleInstaller $installer;

    protected function setUp(): void
    {
        \Db::reset();
        \Configuration::reset();
        $this->module = new \Module();
        $this->module->name = 'lwa_fixture';
        $this->installer = new ModuleInstaller($this->module, new SqlRunner(__DIR__ . '/../../Fixtures', 'ps_', 'InnoDB'));
    }

    public function testRunsEveryStatementWithSubstitution(): void
    {
        self::assertTrue($this->installer->runSqlFile('sql/install.sql'));
        self::assertCount(2, \Db::$executed);
        self::assertStringContainsString('`ps_lwa_fixture_item`', \Db::$executed[0]);
        self::assertStringContainsString('ENGINE=InnoDB', \Db::$executed[0]);
    }

    public function testStopsAtTheFirstFailingStatement(): void
    {
        \Db::$failOn = ['CREATE TABLE'];

        self::assertFalse($this->installer->runSqlFile('sql/install.sql'));
        self::assertCount(1, \Db::$executed);
    }

    public function testRegistersHooksAndReportsFailure(): void
    {
        self::assertTrue($this->installer->registerHooks(['displayBackOfficeHeader', 'actionLwaTemplatePing']));
        self::assertSame(['displayBackOfficeHeader', 'actionLwaTemplatePing'], $this->module->registeredHooks);

        $this->module->failHooks = ['actionBroken'];
        self::assertFalse($this->installer->registerHooks(['actionBroken']));
    }

    public function testSeedsOnlyMissingConfiguration(): void
    {
        \Configuration::updateGlobalValue('LWA_FIXTURE_KEEP', 'existing');

        self::assertTrue($this->installer->seedConfiguration([
            'LWA_FIXTURE_KEEP' => 'default',
            'LWA_FIXTURE_ENABLED' => 1,
        ]));

        self::assertSame('existing', \Configuration::get('LWA_FIXTURE_KEEP'));
        self::assertSame('1', \Configuration::get('LWA_FIXTURE_ENABLED'));
    }

    public function testSeedingNeverTouchesAShopLevelValue(): void
    {
        // Multistore: the merchant set the key for one shop only, so the global scope is
        // empty. The default goes to the global scope and the shop's value stays.
        \Configuration::updateValue('LWA_FIXTURE_KEEP', 'shop value');

        self::assertTrue($this->installer->seedConfiguration(['LWA_FIXTURE_KEEP' => 'default']));

        self::assertSame('shop value', \Configuration::$values['LWA_FIXTURE_KEEP']);
        self::assertSame('default', \Configuration::$global['LWA_FIXTURE_KEEP']);
    }

    public function testSeedingStopsAtTheFirstFailedWrite(): void
    {
        \Configuration::$failOn = ['LWA_FIXTURE_BROKEN'];

        self::assertFalse($this->installer->seedConfiguration([
            'LWA_FIXTURE_BROKEN' => 1,
            'LWA_FIXTURE_AFTER' => 1,
        ]));
        self::assertFalse(\Configuration::hasKey('LWA_FIXTURE_AFTER'));
    }

    public function testAMissingPrestaShopConstantIsALogicError(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('_LWA_NEVER_DEFINED_');
        PsConstant::string('_LWA_NEVER_DEFINED_');
    }

    public function testAScriptWithoutStatementsIsAnError(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('holds no statement');
        $this->installer->runSqlFile('sql/empty.sql');
    }

    public function testForModuleReadsTheStorePathAndPrefixFromPrestaShopConstants(): void
    {
        self::defineModuleConstantsOnce();

        $module = new \Module();
        $module->name = 'Fixtures';

        $installer = ModuleInstaller::forModule($module);

        self::assertTrue($installer->runSqlFile('sql/install.sql'));
        self::assertCount(2, \Db::$executed);
        self::assertStringContainsString('`ps_lwa_fixture_item`', \Db::$executed[0]);
        self::assertStringContainsString('ENGINE=InnoDB', \Db::$executed[0]);
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

<?php

declare(strict_types=1);

namespace LWA\Kit\Tests\Unit\Compat;

use LWA\Kit\Compat\AdminControllerBase;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use PrestaShopBundle\Controller\Admin\FrameworkBundleAdminController;

final class AdminControllerBaseTest extends TestCase
{
    public function testExtendsTheEightBaseWhenNineIsAbsent(): void
    {
        self::assertFalse(class_exists(\PrestaShopBundle\Controller\Admin\PrestaShopAdminController::class, false));
        self::assertTrue(is_subclass_of(AdminControllerBase::class, FrameworkBundleAdminController::class));

        $reflection = new \ReflectionClass(AdminControllerBase::class);
        self::assertTrue($reflection->isAbstract());
    }

    /**
     * Runs in its own process: AdminControllerBase resolves its parent once per process, and
     * the sibling test above already forces the PS8 branch in the main process. Requiring the
     * PS9 fake here, before AdminControllerBase is first touched, makes PrestaShopAdminController
     * exist when the conditional class declaration runs, so this honestly exercises the PS9
     * branch rather than merely re-asserting the PS8 result.
     */
    #[RunInSeparateProcess]
    public function testExtendsTheNineBaseWhenNineIsPresent(): void
    {
        require_once __DIR__ . '/../../Fakes/Ps9/PrestaShopAdminController.php';

        self::assertTrue(class_exists(\PrestaShopBundle\Controller\Admin\PrestaShopAdminController::class, false));
        self::assertTrue(is_subclass_of(AdminControllerBase::class, \PrestaShopBundle\Controller\Admin\PrestaShopAdminController::class));

        $reflection = new \ReflectionClass(AdminControllerBase::class);
        self::assertTrue($reflection->isAbstract());
    }
}

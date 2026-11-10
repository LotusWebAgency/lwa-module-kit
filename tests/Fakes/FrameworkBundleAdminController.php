<?php

declare(strict_types=1);

namespace PrestaShopBundle\Controller\Admin;

if (!class_exists(FrameworkBundleAdminController::class, false)) {
    /**
     * Test double for the PrestaShop 8 base controller. PrestaShopAdminController is
     * deliberately absent so the 8.x branch of AdminControllerBase is the one under test.
     */
    abstract class FrameworkBundleAdminController
    {
    }
}

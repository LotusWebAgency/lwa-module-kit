<?php

declare(strict_types=1);

namespace PrestaShopBundle\Controller\Admin;

// Deliberately NOT under tests/Fakes/*.php: bootstrap.php autoloads every file that matches
// that flat glob, and if this class always existed, AdminControllerBase would always resolve
// to the PS9 branch and the PS8 branch could never be honestly exercised. Required explicitly,
// and only, by AdminControllerBaseTest::testExtendsTheNineBaseWhenNineIsPresent().
if (!class_exists(PrestaShopAdminController::class, false)) {
    /**
     * Test double for the PrestaShop 9 base controller.
     */
    abstract class PrestaShopAdminController
    {
    }
}

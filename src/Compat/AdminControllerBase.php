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

namespace LWA\Kit\Compat;

/*
 * One class name, two parents. PrestaShop 9 ships PrestaShopAdminController (Symfony 6.4,
 * AbstractController, no $this->get()); PrestaShop 8 ships FrameworkBundleAdminController.
 * Module controllers extend this class, take every dependency through the constructor and
 * are registered as services (see the module template), which works on both majors.
 */
if (class_exists(\PrestaShopBundle\Controller\Admin\PrestaShopAdminController::class)) {
    /**
     * PHPStan resolves the first class declaration in a runtime if/else block whichever
     * branch executes, and on a PrestaShop 8 tree that branch's parent does not exist, so
     * every inherited method would read as undefined. These @method tags supply what the
     * unresolvable parent would have; they sit on both branches because which declaration
     * PHPStan resolves depends on the tree being analysed.
     *
     * Only methods that work on both majors are listed. presentGrid() is left out on
     * purpose: on 8.x it reaches for a service the module controller locator does not
     * provide. Inject GridPresenterInterface and call present() instead.
     *
     * @method \Symfony\Component\HttpFoundation\Response render(string $view, array<string, mixed> $parameters = [], ?\Symfony\Component\HttpFoundation\Response $response = null)
     * @method string generateUrl(string $route, array<string, mixed> $parameters = [], int $referenceType = 1)
     * @method \Symfony\Component\Form\FormInterface createForm(string $type, mixed $data = null, array<string, mixed> $options = [])
     * @method void addFlash(string $type, mixed $message)
     * @method \Symfony\Component\HttpFoundation\RedirectResponse redirectToRoute(string $route, array<string, mixed> $parameters = [], int $status = 302)
     */
    abstract class AdminControllerBase extends \PrestaShopBundle\Controller\Admin\PrestaShopAdminController
    {
    }
} else {
    /**
     * PHPStan resolves the first class declaration in a runtime if/else block whichever
     * branch executes, and on a PrestaShop 8 tree that branch's parent does not exist, so
     * every inherited method would read as undefined. These @method tags supply what the
     * unresolvable parent would have; they sit on both branches because which declaration
     * PHPStan resolves depends on the tree being analysed.
     *
     * Only methods that work on both majors are listed. presentGrid() is left out on
     * purpose: on 8.x it reaches for a service the module controller locator does not
     * provide. Inject GridPresenterInterface and call present() instead.
     *
     * @method \Symfony\Component\HttpFoundation\Response render(string $view, array<string, mixed> $parameters = [], ?\Symfony\Component\HttpFoundation\Response $response = null)
     * @method string generateUrl(string $route, array<string, mixed> $parameters = [], int $referenceType = 1)
     * @method \Symfony\Component\Form\FormInterface createForm(string $type, mixed $data = null, array<string, mixed> $options = [])
     * @method void addFlash(string $type, mixed $message)
     * @method \Symfony\Component\HttpFoundation\RedirectResponse redirectToRoute(string $route, array<string, mixed> $parameters = [], int $status = 302)
     */
    abstract class AdminControllerBase extends \PrestaShopBundle\Controller\Admin\FrameworkBundleAdminController
    {
    }
}

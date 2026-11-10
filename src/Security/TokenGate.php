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

namespace LWA\Kit\Security;

/**
 * Gate for module front controllers and AJAX endpoints.
 *
 * Usage in a ModuleFrontController:
 *   $gate = TokenGate::fromConfiguration('LWA_TEMPLATE_TOKEN');
 *   if (!$gate->passes(is_string($v = Tools::getValue('token')) ? $v : null)) {
 *       http_response_code(403);
 *       header('Content-Type: application/json');
 *       $this->ajaxRender(json_encode($gate->denyPayload()));
 *       exit;
 *   }
 */
final class TokenGate
{
    /**
     * Token::generate()'s default of 24 bytes yields 48 hex characters, so 32
     * accepts every generated token while rejecting anything hand-set and weak.
     */
    public const MIN_TOKEN_LENGTH = 32;

    private bool $cliAllowed = false;

    public function __construct(private string $expected)
    {
    }

    public static function fromConfiguration(string $configKey): self
    {
        $value = \Configuration::get($configKey);

        return new self(is_string($value) ? $value : '');
    }

    public function allowCli(): self
    {
        $gate = clone $this;
        $gate->cliAllowed = true;

        return $gate;
    }

    /**
     * @param string|null $provided the token from the request, null when absent
     * @param string $sapi @internal PHP_SAPI, injectable only so tests can simulate a
     *                     console process; callers must never pass request data into it
     */
    public function passes(?string $provided, string $sapi = \PHP_SAPI): bool
    {
        if ($this->cliAllowed && 'cli' === $sapi && !isset($_SERVER['REQUEST_METHOD'])) {
            return true;
        }

        if (strlen($this->expected) < self::MIN_TOKEN_LENGTH) {
            return false;
        }

        if (null === $provided) {
            return false;
        }

        return Token::equals($this->expected, $provided);
    }

    /**
     * @return array{error: string}
     */
    public function denyPayload(): array
    {
        return ['error' => 'forbidden'];
    }
}

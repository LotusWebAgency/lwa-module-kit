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
 * Gate for module front controllers and AJAX endpoints: the request must carry the
 * module's configured token. A configured token shorter than MIN_TOKEN_LENGTH never
 * passes, even when the request supplies it exactly.
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

    public function __construct(#[\SensitiveParameter] private string $expected)
    {
    }

    public static function fromConfiguration(string $configKey): self
    {
        $value = \Configuration::get($configKey);

        return new self(is_string($value) ? $value : '');
    }

    /**
     * Returns a copy that also lets a console process through without a token: PHP runs
     * under the cli SAPI and no web request is in flight. Both are facts of the process,
     * never of the request, so a web client cannot claim them.
     */
    public function allowCli(): self
    {
        $gate = clone $this;
        $gate->cliAllowed = true;

        return $gate;
    }

    /**
     * @param string|null $provided the token from the request, null when absent
     */
    public function passes(#[\SensitiveParameter] ?string $provided): bool
    {
        if ($this->cliAllowed && 'cli' === \PHP_SAPI && !isset($_SERVER['REQUEST_METHOD'])) {
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
     * The JSON body for a refused request. It says nothing about why.
     *
     * @return array{error: string}
     */
    public function denyPayload(): array
    {
        return ['error' => 'forbidden'];
    }
}

<?php

declare(strict_types=1);

// Run by TokenGateTest under a non-cli SAPI, straight from a shell, so no web request is
// in flight. Prints the SAPI and whether a CLI-allowing gate let a tokenless call through.

require __DIR__ . '/../bootstrap.php';

use LWA\Kit\Security\Token;
use LWA\Kit\Security\TokenGate;

$gate = (new TokenGate(Token::generate()))->allowCli();

echo PHP_SAPI, ':', isset($_SERVER['REQUEST_METHOD']) ? 'request' : 'no-request', ':', $gate->passes(null) ? 'open' : 'closed';

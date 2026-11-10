<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

// Fakes stand in for PrestaShop globals. Each file declares its class only if absent.
foreach (glob(__DIR__ . '/Fakes/*.php') ?: [] as $fake) {
    require $fake;
}

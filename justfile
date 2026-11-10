set shell := ["bash", "-euo", "pipefail", "-c"]

# dependency hygiene, lint, static analysis and unit tests (what CI runs)
check: deps cs stan unit

# composer.json/lock hygiene, mirrors the CI lint job (needs network for the advisory feed)
deps:
    composer validate --strict --no-interaction
    composer audit --locked --abandoned=ignore --no-interaction

cs:
    vendor/bin/php-cs-fixer fix --dry-run --diff --config=.php-cs-fixer.dist.php

cs-fix:
    vendor/bin/php-cs-fixer fix --config=.php-cs-fixer.dist.php

stan:
    vendor/bin/phpstan analyse --memory-limit=512M

unit:
    vendor/bin/phpunit

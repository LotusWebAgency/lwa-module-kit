# Contributing

Bug reports, fixes and small improvements are welcome.

## Before you open a pull request

Run the same gates CI runs:

    composer install --no-interaction --no-progress
    just check

`just check` runs `composer validate`, `composer audit`, php-cs-fixer, PHPStan at level 9 and the unit tests. `just cs-fix` applies the code style.

Shipped code is PHP 8.1 syntax only (no readonly classes, DNF types, typed class constants or `#[\Override]`), and the unit tests use the fakes in `tests/Fakes` and never boot PrestaShop. A change to behavior comes with a test.

## How pull requests are handled

This repository is a published copy of the maintainers' working repository. Maintainers review pull requests here on GitHub and apply accepted changes upstream. They reach this repository with the next release, which is tagged `vX.Y.Z`. The pull request is then closed with a link to the release that carries it, so it will not show as merged.

The kit is vendored into modules that cannot take a later kit after release, so API changes are held to a high bar. Open an issue first for anything beyond a fix.

## License

By contributing you agree that your contribution is licensed under the MIT license in `LICENSE`.

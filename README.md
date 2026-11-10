# lwa/module-kit

Build-time helpers bundled into every `lwa_` PrestaShop module and prefixed by
[php-scoper](https://github.com/humbug/php-scoper) at packaging time into
`LwaScoped\<Name>\...`. The kit never runs as a shared runtime dependency and
is never installed as a module itself — every `lwa_` module vendors its own
copy. Because of that, a module can never be updated to a newer kit after
release: whatever version it was built against ships forever, unchanged, for
the life of that module release.

The one exception is `LWA\Queues\Api`, which stays **unscoped** on purpose:
every `lwa_` module in a shop must see the same class when they talk to
`lwa_queues`, so it cannot be prefixed per-module like everything else. Only
scalars and arrays are allowed to cross that boundary.

## Components

`Compat\PsVersion`, `Compat\AdminControllerBase`, `Security\Token`,
`Security\TokenGate`, `Install\SqlScript`, `Install\ModuleInstaller`,
`Install\ModuleUninstaller`, `Clock`, `Queue\Queue`, `Queue\HandlesQueueJobs`,
`Queue\Job`, `Queue\InlineJobContext`.

## Security

`Security\TokenGate` always refuses a configured token shorter than
`TokenGate::MIN_TOKEN_LENGTH` (32 characters), even if the caller supplies it
exactly — tokens must come from `Token::generate()`, whose 16-byte floor
produces at least 32 hex characters.

## Install / uninstall SQL

Install and uninstall SQL files, as parsed by `Install\SqlScript::split()`,
must end every statement with `;` followed by a newline. A statement may span
as many lines as it likes — a multi-line `CREATE TABLE` is fine. Full-line
`--` comments are fine, but no inline trailing `-- comment` and no newlines
inside string literals.

## Queue bridge

`Queue\Queue` bridges to the lwa_queues module and has sharp edges module
authors need to know about:

- An inline job (run when lwa_queues is missing, disabled, broken, or a
  version this kit isn't compatible with) executes synchronously inside
  `dispatchOrRun()`, and any exception it raises propagates straight into the
  caller's hook — this is intentional, not a bug, because silently swallowing
  a failed inline job would be invisible data loss, but it does mean a hook
  can 500 the page.
- `InlineJobContext::release()` always throws: it has nothing to release to.
  A job that may call `$context->release()` must never be dispatched through
  `dispatchOrRun()`, only through a real queue.
- `Queue::dispatch()` returning `null` means the job was not queued, and it
  does not say why: there may be no queue at all (`lwa_queues` missing,
  disabled, or speaking an API version this kit was not built against), or
  the queue may have refused the job or failed after accepting it. Do not
  treat `null` as "there is no queue" and re-run the job yourself — that is
  exactly the double-execution `dispatchOrRun()` avoids. `dispatchOrRun()`
  asks `Queue::available()` itself and runs a job inline only when that is
  false; when the queue was available and still returned `null`, it logs and
  returns `null`. `Queue::unavailableReason()` tells you whether the cause
  was actionable.
- `\Module::isEnabled()` is cached for the whole PrestaShop request
  (`Cache::store`). A module's `install()` that enables lwa_queues and then
  calls `dispatchOrRun()` in that same request will still get inline
  execution for the rest of that request — the cache only picks up the new
  state on the next one.

## Rules

PHP 8.1 syntax, no PrestaShop class referenced without a `class_exists` guard
or a constructor argument, every public method unit-tested with the fakes in
`tests/Fakes`.

## Consuming

    composer require lwa/module-kit:^0.2

The kit is published on Packagist. It is a build-time dependency: a module must
not ship it as-is, it has to prefix it with php-scoper first.

### Why a module prefixes the kit

Several `lwa_` modules are loaded into one PHP process, and each vendors its
own kit version. Without a prefix, the first module to load `LWA\Kit\Queue\Queue`
would win and every other module would silently run against the wrong version.
Prefixing gives each module a private copy under `LwaScoped\<Name>\LWA\Kit\*`.
`LWA\Queues\Api` is the one deliberately unscoped class: all modules must see
the same one when they talk to `lwa_queues`.

### How

Add a `scoper.inc.php` at the module root:

- `prefix` is `LwaScoped\<Name>`.
- The finders cover the root `*.php` files and `composer.json`, `src/`,
  `controllers/`, `upgrade/`, `sql/` and `vendor/`. An upgrade script that is
  not scoped fatals during a merchant's upgrade, because the package only
  contains the prefixed classes. Guard each optional directory with `is_dir()`,
  since `Finder::in()` throws on a missing one. The `vendor/` finder skips
  `doc`, `docs`, `test`, `tests`, `Tests` and `examples`, and files named
  `LICENSE`, `*.md`, `*.dist`, `Makefile`, `CHANGELOG` or `UPGRADE`.
- `exclude-namespaces` keeps the real name of: the global namespace (`~^$~`, so
  `\Module` and other global classes still resolve), `PrestaShop`,
  `PrestaShopBundle`, `Symfony`, `Doctrine`, `Twig`, `Psr`, `LWA\Queues` and the
  module's own namespace.
- `exclude-constants` keeps `_PS_*`, `_DB_*`, `_MYSQL_*` and `_COOKIE_*`, and
  `expose-global-constants`, `expose-global-classes` and
  `expose-global-functions` are `false`. Without these, a module that reads
  `_PS_VERSION_` or extends `\Module` fails at runtime.

The worked example below is authoritative: if this list and its
`scoper.inc.php` ever disagree, follow the example.

`config/*.yml` and integration tests are not rewritten by the scoper, so they may
name only the module's own classes and PrestaShop or Symfony services.

[`lwa_queues`](https://github.com/LotusWebAgency/lwa_queues) is the worked
example: see its `scoper.inc.php` and the `build` recipe in its `justfile`.

## Local check

`composer install && just check`.

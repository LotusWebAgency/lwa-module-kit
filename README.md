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

| Class | What it is for |
|---|---|
| `Clock` | UTC storage, shop time at display. |
| `Security\Token`, `Security\TokenGate` | Shared-secret tokens and the gate every front controller and AJAX endpoint goes through. |
| `Install\ModuleInstaller`, `Install\ModuleUninstaller`, `Install\SqlScript` | The steps of `install()` and `uninstall()`. |
| `Compat\AdminControllerBase`, `Compat\PsVersion` | One admin controller parent for PrestaShop 8 and 9; version checks. |
| `Queue\Queue`, `Queue\HandlesQueueJobs`, `Queue\Job`, `Queue\InlineJobContext` | The bridge to `lwa_queues`. |

Everything under `Internal\`, and `Install\SqlRunner`, is `@internal`: a module must not
call it.

## Clock

    $now = Clock::utcNow();                          // '2026-11-11 09:30:00', what goes into a DATETIME column
    $shown = Clock::forShop()->toShopTime($row['created_at']);
    $stored = Clock::forShop()->toUtc('2026-11-11 12:30:00');

`toShopTime()` and `toUtc()` accept exactly `Y-m-d H:i:s` and a real calendar date.
Anything else throws `\InvalidArgumentException`: an empty string, `0000-00-00 00:00:00`,
`tomorrow`, a value with an offset, February 31. Check a nullable column for `null`
before converting it.

## Security

    $gate = TokenGate::fromConfiguration('LWA_FOO_TOKEN');
    if (!$gate->passes(is_string($token = Tools::getValue('token')) ? $token : null)) {
        http_response_code(403);
        header('Content-Type: application/json');
        $this->ajaxRender(json_encode($gate->denyPayload()));
        exit;
    }

`Security\TokenGate` always refuses a configured token shorter than
`TokenGate::MIN_TOKEN_LENGTH` (32 characters), even if the caller supplies it
exactly. Tokens must come from `Token::generate()`, whose 16-byte floor
produces at least 32 hex characters. `allowCli()` returns a gate that also lets
a console process through without a token.

## Install / uninstall

    // install()
    $installer = ModuleInstaller::forModule($this);

    return parent::install()
        && $installer->runSqlFile('sql/install.sql')
        && $installer->registerHooks(['actionCronJob'])
        && $installer->seedConfiguration(['LWA_FOO_ENABLED' => 1]);

    // uninstall()
    return parent::uninstall()
        && ($keepData || ModuleUninstaller::forModule($this)->run('sql/uninstall.sql', Settings::keys()));

- `runSqlFile()` and `run()` return `false` as soon as a statement fails. Statements
  already executed stay applied, so write re-runnable SQL (`CREATE TABLE IF NOT EXISTS`,
  `DROP TABLE IF EXISTS`). An unreadable file, or one with no statement in it, throws
  `\RuntimeException`.
- `seedConfiguration()` writes shop-wide defaults for keys that have no global value yet.
  It never overwrites, and never writes into a single shop's scope.
- `ModuleUninstaller::run()` deletes the configuration keys only after the SQL succeeded.

### SQL files

Install and uninstall SQL files, as parsed by `Install\SqlScript::split()`,
must end every statement with `;` followed by a newline. A statement may span
as many lines as it likes — a multi-line `CREATE TABLE` is fine. Full-line
`--` comments are the only comment form: no inline trailing `-- comment`, no
`#`, no `/* */`. No newlines inside string literals. `PREFIX_` and
`ENGINE_TYPE` are replaced wherever they appear, string literals included.

## Admin controllers

A module's admin controller extends `Compat\AdminControllerBase`, takes every
dependency through its constructor and is registered as a service. Use only
`render`, `generateUrl`, `createForm`, `addFlash` and `redirectToRoute` from the
parent: those work on both majors. `presentGrid()` does not work on 8.x; inject
`GridPresenterInterface` and call `present()`.

## Queue bridge

`Queue\Queue` bridges to the lwa_queues module and has sharp edges module
authors need to know about:

    // In the module main class
    use HandlesQueueJobs;

    protected function lwaQueueHandlers(): array
    {
        return ['sync-product' => SyncProductJob::class];   // SyncProductJob implements Queue\Job
    }

    // Anywhere in the module
    Queue::dispatchOrRun($this, 'sync-product', ['id_product' => 42]);

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

PHP 8.1 syntax. The queue bridge must load and degrade where PrestaShop or
`lwa_queues` is absent or broken, so it reaches PrestaShop classes only behind
`class_exists()`; the other components assume a booted shop. Every public
method is unit-tested with the fakes in `tests/Fakes`. The fakes are hand-written, so
the same assumptions are checked against the real PrestaShop classes by
`tests/Integration/KitContractTest.php` in `module-template`, which the matrix
runs on every PrestaShop cell. PHPStan runs at level
`max` with strict rules over PHP 8.1 to 8.5.

## Consuming

    composer require lwa/module-kit:^0.3

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

# Changelog

## 0.2.0 - 2026-11-11

- License headers name LW AGENCY LIMITED as the copyright holder.
- PHPStan level 9 over PHP 8.1 to 8.5.
- Available on Packagist as `lwa/module-kit`.
- No API change.

## 0.1.0 - 2026-09-10

- First components: PsVersion, AdminControllerBase, Token, TokenGate, SqlScript, ModuleInstaller, ModuleUninstaller, Clock, Queue bridge, HandlesQueueJobs.
- TokenGate always refuses a configured token shorter than 32 characters, even when the caller supplies it exactly; tokens must come from `Token::generate()`.
- Documented the SQL authoring contract `SqlScript::split()` requires: every statement ends with `;` followed by a newline (statements may span lines), no inline trailing comments, no newlines inside string literals.
- `Queue::available()` no longer propagates an exception from a broken lwa_queues provider (e.g. a partial deploy missing its scoped autoloader); it degrades to unavailable and logs once instead.
- `Queue::dispatchOrRun()` decides whether to run a job inline from `Queue::available()`, not from `dispatch()`'s return value, so a queue that accepted a dispatch but failed afterwards (or a future provider that deliberately refuses a job) is never run a second time inline.
- Added `Queue::unavailableReason()` and a once-per-request log line for an lwa_queues present but speaking an API version this kit isn't compatible with, so a version mismatch (a real misconfiguration) is no longer silent the way the ordinary "not installed" state stays.
- `Queue` now prefers a provider's own `Api::supportsVersion()` over an exact match on `Api::API_VERSION`, so a later lwa_queues can declare continued support for an already-shipped module's API version instead of breaking it outright. The answer must be exactly `true`, the method must actually be static, and a provider whose `supportsVersion()` throws degrades to unavailable rather than propagating.
- Sanitized untrusted queue handler names before they reach a log line or exception message, at every site, cutting on character boundaries and stripping the Unicode line separators (U+0085, U+2028, U+2029) as well as ASCII control characters.
- Documented the queue bridge's operational gotchas in the README.

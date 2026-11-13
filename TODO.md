# Agent Execution Plan: PHP 7.3 (WAMP) & PHP 8.3+ (LAMP) Dual-Compatibility

> **Status (2026-11-13): carried out on `dev` with the PHP 7.3 floor.** Every item below is ticked with its result.
> Four follow-ups are still open and marked `[ ]`: 2.2a, 3.3a, 4.2a and 5.3a.
> Gates: `composer run check` (static), `composer run lint:php73` / `lint:docker:73` (real PHP 7.3 syntax) and
> `composer run tests:matrix` (PHPUnit on PHP 7.3, 7.4 and 8.3). Details: AGENTS.md section 2 (rules 1, 4, 5) and section 6.

## 1. Mission & Core Directives
You are operating on a fragile legacy PHP codebase. 
- **Production Environment:** PHP 7.3 on Windows (WAMP). **MUST NOT BREAK.**
- **Dev Environment before this plan:** PHP 7.4 on Linux (LAMP dev-container).
- **Dev Environment now:** PHP 8.3 on Linux (LAMP dev-container), with `php7.3` as a second CLI.
- **Primary Goal:** Upgrade the dev-container to PHP 8.3+ and refactor the codebase so it runs cleanly without errors on **both** PHP 7.3 and PHP 8.3+ simultaneously.

###The Golden Rules (STRICT ENFORCEMENT)
1. **NO PHP 7.4+ Syntax:** Never introduce typed properties (`public string $name`), arrow functions (`fn()`), nullsafe operators (`?->`), `match` expressions, union types (`int|string`), named arguments, constructor property promotion, or PHP 8 Attributes (`#[...]`). Every line of code must parse cleanly in PHP 7.3.
2. **NO Unchecked `composer update`:** Do not upgrade packages to versions that drop PHP 7.3 support.
3. **OS-Agnostic Paths Only:** Never hardcode `\` or `/`. Use `DIRECTORY_SEPARATOR` or standard forward slashes inside PHP stream functions, and ensure all `require`/`include`/PSR-4 class names match exact file casing on disk.

---

## 2. Phase 1: Baseline Audit & Static Analysis Setup (Zero Code Changes)
Before altering any application code or container configs, establish automated guardrails.

- [x] **1.1 Install PHPCompatibility & PHP_CodeSniffer (Dev-Only):**
  - Run inside the dev environment (or as a standalone phar/composer global tool so `composer.json` isn't polluted if fragile):
    `composer require --dev dealerdirect/phpcodesniffer-composer-installer phpcompatibility/php-compatibility`
  - **Result:** `phpcompatibility/php-compatibility` 10.0.0-alpha2 (with PHPCSUtils and the Composer installer plugin) and `squizlabs/php_codesniffer` 3.13 are dev dependencies. 9.3.5, the last stable release (2019), has no PHP 8 sniffs. Ruleset: `phpcs.xml.dist` (now also scans the `*.html` views). Run: `composer run lint`.
- [x] **1.2 Run Dual-Target Compatibility Scan:**
  - Execute PHPCS targeting the full range from 7.3 to 8.3:
    `./vendor/bin/phpcs -p . --standard=PHPCompatibility --runtime-set testVersion 7.3-8.3 --ignore=vendor/,node_modules/`
  - Export the findings to `audit-php83-blockers.txt`.
  - **Result:** `composer run audit:php83` (inside a container) or `composer run audit:docker` (from the host) writes `audit-php83-blockers.txt`. Blockers for PHP 7.3 - 8.3: **0**. The report also lists 46 warnings that only concern PHP 8.4 / 8.5 (31 `(double)` casts, 13 `fgetcsv()` calls without `$escape`, 2 `curl_close()`); they are informational.
- [x] **1.3 Configure PHPStan (Level 0 to Level 2):**
  - Set up `phpstan.neon` with `phpVersion: 70300` (to catch syntax violations) and a second profile `phpstan-83.neon` with `phpVersion: 80300` (to catch internal function signature mismatches).
  - **Result:** `phpstan.neon` (`phpVersion: 70300`) and `phpstan-83.neon` (`80300`), level 2, sharing `phpstan-baseline.neon`. The baseline holds the 302 findings that existed before; 265 of them are variables a script receives from the file that includes it (`$con`, view data). Run: `composer run stan`, `composer run stan:83`.
- [x] **1.4 WAMP/LAMP Case-Sensitivity Audit:**
  - Scan all `include`, `require`, `include_once`, `require_once`, and class autoload maps to verify exact case matching between code references and actual filenames on disk.
  - **Result:** `composer run lint:case` (`dev.tool/audit-case.php`): 53 `include`/`require` statements and 110 local URL references, **0** case mismatches. 13 targets exist in no casing at all: the untracked legacy `process/_*.php` partials (AGENTS.md section 8) and a stylesheet link in two unused `css/*.html` samples.

---

## 3. Phase 2: Dependency & Composer Dual-Version Strategy
Ensure `vendor/` packages work on both PHP 7.3 and PHP 8.3.

- [x] **2.1 Audit Current `composer.json` & `composer.lock`:**
  - Identify any packages in `composer.lock` that fail on PHP 8.3 (e.g., old versions of `phpunit`, `guzzlehttp`, `monolog`, `symfony/*`, or `laravel/*`).
  - **Result:** the previous lock worked on neither end. On PHP 8.3 `composer install` refused it (`phpunit/phpunit` 9.5.0 needs `phpspec/prophecy`, which excludes 8.3). On PHP 7.3, 19 files of `phpoffice/phpspreadsheet` 1.29.4 (requires PHP >= 7.4) are parse errors.
- [x] **2.2 Pin Platform Version to 7.3.33:**
  - In `composer.json`, set:
    ```json
    "config": {
        "platform": {
            "php": "7.3.33"
        }
    }
    ```
  - *Why:* Even when running `composer install` or `composer update` inside the PHP 8.3 dev-container, Composer will refuse to download package versions that require PHP 7.4+, protecting the WAMP production server.
  - **Result:** done. The re-resolved lock has PhpSpreadsheet 1.25.2 (the last release for PHP 7.3), zipstream-php 2.1.0 and PHPUnit 9.6.39. The pin only guards the floor, so every test run also executes `composer check-platform-reqs` against the PHP that is actually running (`dev.tool/run-tests.sh`).
- [ ] **2.2a Open decision - the PHP floor and PhpSpreadsheet:** 1.25.2 has 31 known security advisories; the fixed line (1.30.7+) needs PHP >= 7.4. Run `php -v` on the WAMP host. If it reports 7.4 or later, follow AGENTS.md section 2, rule 5 (platform pin, `composer update`, drop the 7.3 leg).
- [x] **2.3 Upgrade Polyfills & Dual-Compatible Packages:**
  - Add `symfony/polyfill-php80`, `symfony/polyfill-php81`, `symfony/polyfill-php82`, and `symfony/polyfill-php83` if safe helper functions (like `str_contains()`, `str_starts_with()`, `array_is_list()`) are needed.
  - Incrementally update specific dependencies (`composer update vendor/package --with-dependencies`) to versions that support `^7.3 || ^8.0`, running `php -l` across `vendor/` using PHP 7.3 to verify no syntax leaks.
  - **Result:** no polyfill added. First-party code calls no PHP 8-only function, and without polyfills `composer run lint` keeps reporting such calls. `php -l` with real PHP 7.3.33 over the production vendor packages: 805 files, 0 errors (`composer run lint:php73` in the dev container, `composer run lint:docker:73` from the host). One file is skipped because it is PHP 8 only by design and never loaded by PHP 7: `symfony/polyfill-mbstring/bootstrap80.php`.

---

## 4. Phase 3: Codebase Refactoring for PHP 8.0–8.3 Compatibility
Fix issues identified in `audit-php83-blockers.txt` using strictly PHP 7.3-valid syntax.

- [x] **3.1 Fix PHP 8.2+ Dynamic Properties Deprecation:**
  - Locate all classes where properties are assigned dynamically (`$this->undeclaredProp = $val`).
  - **Fix:** Explicitly declare `public $undeclaredProp;` or `protected $undeclaredProp;` (WITHOUT type hints) at the top of the class.
  - For magic `__get`/`__set` data containers or DTOs, add the `#[\ReturnTypeWillChange]` or `#[\AllowDynamicProperties]` attribute ONLY because `#` is parsed as a harmless single-line comment in PHP 7.3! *(CRITICAL: Ensure `#[\AllowDynamicProperties]` is on its own line with nothing after it on that line so PHP 7.3 ignores it as a comment).*
  - **Result:** nothing to fix. No class creates a dynamic property (AST scan of every class; full test suite on PHP 8.3 with deprecations as failures), so no `#[\AllowDynamicProperties]` was needed.
- [x] **3.2 Fix Internal Interface & Method Signatures (`ReturnTypeWillChange`):**
  - Classes implementing `ArrayAccess`, `Iterator`, `Countable`, `JsonSerializable`, or `SessionHandlerInterface` now require tentative return types in PHP 8.1+.
  - Since PHP 7.3 does not support `mixed` return types (e.g., `offsetGet($offset): mixed` is a fatal syntax error in PHP 7.3), place `#[\ReturnTypeWillChange]` on its own line directly above the method declaration:
    ```php
    #[\ReturnTypeWillChange]
    public function offsetGet($offset)
    ```
    *Note: In PHP 7.3, `#[\ReturnTypeWillChange]` is treated as a comment (`# ...`). In PHP 8.1+, it suppresses the return type deprecation notice.*
  - **Result:** `#[\ReturnTypeWillChange]` above `offsetGet()` in `ICollection` and `LazyCollection`, the only implementation of an internal interface. Golden Rule 1 lists attributes among the forbidden syntax; this is the one exception, as this item prescribes.
- [x] **3.3 Remediate Strict TypeErrors on Built-in Functions (PHP 8.0+):**
  - `count($var)` / `sizeof($var)`: Wrap or guard with `is_countable($var) ? count($var) : 0`.
  - `strlen()`, `trim()`, `htmlspecialchars()`, `preg_match()`, `explode()`: PHP 8.1 deprecates passing `null` to non-nullable internal string functions. Cast explicitly: `trim((string) $val)` or `htmlspecialchars((string) $val, ENT_QUOTES, 'UTF-8')`.
  - `array_merge()`, `in_array()`, `array_key_exists()`: Ensure arguments are validated with `is_array()` before invocation, and replace `array_key_exists($key, $object)` (removed in PHP 8.0) with `property_exists($object, $key)` or `isset()`.
  - **Result:** casts and guards where `null` or a non-number reaches a built-in at run time, each covered by a test that fails when the fix is removed: `TableDisplayer`, `RecordEditor`, `StockOutTable`, `Product\Item`, `Orders\Record`, `Orders\Factory\MonthlyRecord`, `ShippedItemReport`, `StockBookmark`, `CSV_Table_Handler`, `catalog_generator`, `StockReport`, `Lzd_Fee_Matcher` and `request_handler/RecordUpdater.php`. `array_key_exists()` on an object: 0 occurrences.
- [ ] **3.3a Open - four inputs still behave differently on PHP 7 and PHP 8:** an empty CSV upload in `Orders\Factory\Lazada`, `Orders\Factory\Shopee` and `Lazada\Factory\LzdItemFactory` (`count(false)`), and an invalid stored date in `CashsaleText` (`date_format(false)`): a warning on PHP 7, a `TypeError` on PHP 8. Each is pinned by a `documentsCurrentBehaviour` test, and the three uploads also have a `known-bug` test describing the intended behaviour. Not changed, because fixing them changes what production does for those inputs.
- [x] **3.4 Fix Parameter Order:**
  - PHP 8.0+ deprecates required parameters declared after optional parameters (e.g., `function foo($a = null, $b)`).
  - Refactor signatures so defaults are either removed from preceding parameters or explicitly added to trailing ones while checking all call sites.
  - **Result:** 0 occurrences (PHPCompatibility).
- [x] **3.5 Replace Removed Legacy Functions & Constructs:**
  - Replace `implode($pieces, $glue)` with `implode($glue, $pieces)`.
  - Replace curly brace string/array offset access `$str{0}` with `$str[0]`.
  - Replace `each()` loops with `foreach`.
  - Replace `strftime()` / `gmstrftime()` (deprecated in PHP 8.1) with `date()` or `IntlDateFormatter`.
  - Replace `utf8_encode()` / `utf8_decode()` (deprecated in PHP 8.2) with `mb_convert_encoding()`.
  - **Result:** 0 occurrences of any of them (PHPCompatibility).

---

## 5. Phase 4: Dev-Container Upgrade (PHP 7.4 -> PHP 8.3+)
Update `.devcontainer/` without altering production deployment scripts.

- [x] **4.1 Create Multi-Version or Upgraded `.devcontainer/Dockerfile`:**
  - Update base image from `php:7.4-apache` (or `-fpm`) to `php:8.3-apache`.
  - Install a secondary PHP 7.3 CLI binary (via `ondrej/php` PPA inside the container) OR configure a Git pre-commit hook / CI step that runs `php7.3 -l` on every modified file.
  - **Result:** the single root `Dockerfile` takes a `PHP_VERSION` build arg. The default stays 7.4, so `setup.staging.bat` / `setup.production.bat` build the same image as before. `compose.yml` builds the dev container with `PHP_VERSION=8.3` and `FLOOR_PHP_CLI=7.3`, which adds `/usr/bin/php7.3` from deb.sury.org. Opt-in pre-commit hook: `git config core.hooksPath .githooks`.
- [x] **4.2 Align `php.ini` Extensions with Production WAMP:**
  - Inventory active extensions on production WAMP (`php -m` on WAMP) and ensure matching extensions (`mysqli`, `pdo_mysql`, `gd`, `intl`, `mbstring`, `curl`, `zip`, `opcache`) are compiled and enabled in the PHP 8.3 dev-container.
  - **Result:** the PHP 7.3, 7.4 and 8.3 images load `mysqli`, `gd` (FreeType, JPEG, PNG), `zip`, `mbstring`, OPcache and Xdebug, and `composer check-platform-reqs` verifies the extensions of the locked packages on every test run. Application code itself only needs `mysqli`; it uses neither `intl` nor `pdo_mysql`.
- [ ] **4.2a Open - on the WAMP host:** `php -m`, to compare the production extensions with the list above.
- [x] **4.3 Configure Development Error Reporting:**
  - Set dev-container `php.ini` to:
    `error_reporting = E_ALL`
    `display_errors = On`
    `log_errors = On`
  - Any `E_DEPRECATED` or `E_WARNING` emitted in the PHP 8.3 dev-container must be treated as a bug and resolved.
  - **Result:** already the case: the tracked `config.dev/php-dev-override.ini` sets all three, and PHP 8.3 starts cleanly with it. "Treated as a bug" is enforced: `phpunit.xml.dist` converts deprecations to failures, and the HTTP smoke tests fail on any warning, notice or deprecation printed into a page.

---

## 6. Phase 5: Verification & Dual-Runtime Gatekeeping
Before any branch can be merged or deployed to the PHP 7.3 WAMP production server:

- [x] **5.1 Automated PHP 7.3 Syntax Linter (Mandatory Gate):**
  - Run `find . -name "*.php" -not -path "./vendor/*" -exec php7.3 -l {} \;` (either via secondary CLI binary in container or Docker one-liner `docker run --rm -v $(pwd):/app php:7.3-cli find /app ...`).
  - **Zero `ParseError`s allowed.**
  - **Result:** `composer run lint:php73` (dev container) and `composer run lint:docker:73` (host, PHP 7.3.33 image): 249 first-party files and 805 vendor files, 0 errors.
- [x] **5.2 Automated PHP 8.3 Syntax & Deprecation Check:**
  - Run the same lint check and test suite under PHP 8.3 CLI.
  - **Result:** `composer run check:docker` (static gates on PHP 8.3) and `composer run tests:matrix` (PHPUnit on PHP 7.3, 7.4 and 8.3; a deprecation fails the run): 1055 tests pass on each version.
- [x] **5.3 Smoke Test Critical Flows:**
  - Authentication / Session handling.
  - Database queries (PDO / MySQLi fetch modes and error modes—note that PHP 8.1+ changed default MySQLi error mode to `MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT` and PDO to `PDO::ERRMODE_EXCEPTION`; explicitly configure these in your DB bootstrap file so PHP 7.3 and PHP 8.3 behave identically!).
  - File uploads and path resolution on both Linux and Windows.
  - **Result:**
    - Authentication / sessions: the application has none (no `session_start()`, `$_SESSION` or cookies).
    - Database: mysqli only, no PDO. `mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT)` was already explicit in both branches of `db/conn_staff.php`. The test database runs with `mysql_native_password`, because PHP 7.3 cannot authenticate against MySQL 8's default plugin.
    - Uploads and pages: `tests/Smoke/` over HTTP on all three versions: every nav route, the listing pages with rows of the seeded database, and the `/RecordUpdater` upload.
- [ ] **5.3a Open - on the WAMP host:** after `composer install --no-dev`, run `composer check-platform-reqs --no-dev` and `php dev.tool/lint-syntax.php --with-vendor`, then upload one daily-order file per platform and one stock `.txt`. Windows path handling cannot be tested from Linux.
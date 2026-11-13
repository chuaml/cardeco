# Tests

PHPUnit 9 suite for Cardeco. Configuration lives in [`phpunit.xml.dist`](../phpunit.xml.dist); copy it to
`phpunit.xml` (gitignored) for local overrides.

## Suites

| Suite | Directory | Needs | Run with |
| :--- | :--- | :--- | :--- |
| `unit` | `tests/Unit/`, `tests/array_extension/` | nothing (no DB, no `ext-zip`) | `composer run tests:unit` (host PHP is fine) |
| `integration` | `tests/Integration/` + the pre-existing `tests/{lazada,bigseller,tiktok,monthly_cashsales_record}/` | MySQL (`MYSQL_*` env) + `ext-zip` | `composer run tests:docker -- --testsuite integration` |
| `smoke` | `tests/Smoke/` | a running Apache (`APP_BASE_URL`); MySQL too for the tests that render or change rows | `composer run tests:docker -- --testsuite smoke` |

`composer run tests:docker` (no arguments) runs everything.

## The Docker test stack

[`compose.test.yml`](../compose.test.yml) starts a **throwaway** `mysql:8.0.41` on tmpfs (database `cardeco_test`,
seeded from `db/cardeco.sql.gz` + `db/upgrade.sql` — the same files as the dev stack) and one PHPUnit service per
PHP runtime, all built from the `dev_app` target of the `Dockerfile` with a different `PHP_VERSION` build arg.
It never touches `cardeco_dev` or its volume.

| Service | PHP | Why | Run with |
| :--- | :--- | :--- | :--- |
| `phpunit` | 8.3 | the dev container runtime | `composer run tests:docker` |
| `phpunit74` | 7.4 | the staging / planned production image | `composer run tests:docker:74` |
| `phpunit73` | 7.3 | the WAMP production floor | `composer run tests:docker:73` |

```bash
composer run tests:matrix                          # all three legs (7.3, 7.4, 8.3) - the gate before merging
composer run tests:docker                          # full suite on PHP 8.3 (first run builds the image, ~5 min)
composer run tests:docker -- tests/Unit/Orders     # any PHPUnit arguments are passed through
composer run tests:docker:73 -- --filter RecordInserter
composer run tests:docker -- --group known-bug     # see the documented bugs fail on purpose
composer run tests:docker:down                     # stop and remove the throwaway database and vendor volumes
composer run check:docker                          # static gates in the PHP 8.3 image (phpcs, php -l, case audit, PHPStan)
composer run lint:docker:73                        # real `php -l` in the PHP 7.3 image
```

`mysql-test` runs with `mysql_native_password`: PHP 7.3 cannot authenticate against MySQL 8's default
`caching_sha2_password` plugin.

The entrypoint [`dev.tool/run-tests.sh`](../dev.tool/run-tests.sh) installs Composer dependencies into a
container-only `vendor` volume (again whenever `composer.lock` changes), regenerates the classmap, runs
`composer check-platform-reqs` against the real PHP, starts Apache for the smoke suite, runs PHPUnit, and
chowns anything it created (golden files, result cache) back to the host user.

## Conventions

- **Characterization first.** Tests lock what the code does today with concrete values. Name them
  `test<Method>_<Scenario>_<Expectation>`.
- **`@group known-bug`** marks a test that describes the *intended* behaviour of a documented bug. The group
  is excluded from the default run (`phpunit.xml.dist`), so the suite stays green while the bug is open; a fix
  is complete when the test passes and the tag is removed. Each such test cites the `file:line` of the bug.
- **`DatabaseTestCase`** ([`tests/Support/DatabaseTestCase.php`](Support/DatabaseTestCase.php)) is the base
  class for DB tests: one shared connection per process, every test wrapped in a transaction that is rolled back,
  automatic skip when `MYSQL_*` is unset, refusal to run against the production database name, and fixture
  helpers (`insertStockItem`, `insertSellerSku`, `insertBigSellerSkuMap`, `insertLzdProduct`, `insertOrder`,
  `fetchAll`). Everything you insert must use the `ZZTEST-` prefix. Classes that manage their own transaction
  set `$useTransaction = false` and delete their `ZZTEST-` rows in `tearDown()`.
- **Golden files** (`tests/Integration/OrderProcess/golden/*.html`) hold the full HTML of the four daily-order
  processors for the sample files against the pristine seed. Regenerate deliberately, review the diff, commit:
  `CARDECO_UPDATE_GOLDEN=1 composer run tests:docker -- --filter Golden`.
- **Fixtures**: prefer synthetic files built at test time with `test\Support\XlsxFixture` / `CsvFixture`
  (`CsvFixture::row($width, [col => value])` for positional parsers). Committed fixtures live next to the tests;
  use `.tsv` for tab-delimited SQL Accounting exports because `*.txt` is gitignored. Never commit real customer
  data.
- **HTTP tests** (`tests/Smoke/`) use the `test\Support\HttpRequests` trait (`httpGet`, `httpPostFile`,
  `assertNoPhpErrors`). `assertNoPhpErrors` recognises the three formats PHP prints a message in: Apache's HTML
  (`<b>Warning</b>:  ...`), plain text (`Warning: ...`) and Xdebug's table (the dev container); searching a page
  for the plain-text form alone finds nothing under Apache. `RouteSmokeTest` opens every nav route without parameters; `SeedDataSmokeTest` opens the
  listing pages with rows of the seeded database (read-only); `RecordUpdaterUploadTest` posts a file to a Tier 2
  script. A request script (`request_handler/*.php`, `process/*.php`) cannot be unit-tested, so a behaviour that
  must hold on every PHP version is asserted over HTTP: status 200 and no warning / notice / deprecation text in
  the page. Fixtures such a test needs must be committed (`$useTransaction = false`) and deleted in `tearDown()`.
- **`UnconnectedMysqli::create()`** gives a real but unconnected `mysqli` for unit-testing methods of classes
  whose constructor type-hints `mysqli` but which never query (e.g. `TikTokOrder::getOrders()`).
- **PHP 7.3 syntax** applies to test code too (`composer run lint` covers `tests/`).
- **Every test must pass on PHP 7.3, 7.4 and 8.3.** `phpunit.xml.dist` sets `convertDeprecationsToExceptions="true"`:
  a deprecation raised by the code under test (typically `null` passed to a string function on PHP 8.1+) fails the
  test. That is the point; fix the code, do not silence the test.
- **Behaviour that legitimately differs between PHP 7 and 8** (a warning on 7.x that is a `TypeError` on 8.x) is
  characterised with `test\Unit\Support\PhpErrors::capture()` and a `PHP_VERSION_ID` branch, never with
  PHPUnit's deprecated `expectWarning()` / `expectNotice()`.

# Tests

PHPUnit 9 suite for Cardeco. Configuration lives in [`phpunit.xml.dist`](../phpunit.xml.dist); copy it to
`phpunit.xml` (gitignored) for local overrides.

## Suites

| Suite | Directory | Needs | Run with |
| :--- | :--- | :--- | :--- |
| `unit` | `tests/Unit/`, `tests/array_extension/` | nothing (no DB, no `ext-zip`) | `composer run tests:unit` (host PHP is fine) |
| `integration` | `tests/Integration/` + the pre-existing `tests/{lazada,bigseller,tiktok,monthly_cashsales_record}/` | MySQL (`MYSQL_*` env) + `ext-zip` | `composer run tests:docker -- --testsuite integration` |
| `smoke` | `tests/Smoke/` | a running Apache (`APP_BASE_URL`) | `composer run tests:docker -- --testsuite smoke` |

`composer run tests:docker` (no arguments) runs everything.

## The Docker test stack

[`compose.test.yml`](../compose.test.yml) starts a **throwaway** `mysql:8.0.41` on tmpfs (database `cardeco_test`,
seeded from `db/cardeco.sql.gz` + `db/upgrade.sql` — the same files as the dev stack) and a PHP 7.4 `phpunit`
service built from the `dev_app` target of the `Dockerfile`. It never touches `cardeco_dev` or its volume.

```bash
composer run tests:docker                          # full suite (first run builds the image, ~5 min)
composer run tests:docker -- tests/Unit/Orders     # any PHPUnit arguments are passed through
composer run tests:docker -- --filter RecordInserter
composer run tests:docker -- --group known-bug     # see the documented bugs fail on purpose
composer run tests:docker:down                     # stop and remove the throwaway database
composer run lint                                  # PHP 7.3 compatibility (PHPCompatibility via phpcs)
```

The entrypoint [`dev.tool/run-tests.sh`](../dev.tool/run-tests.sh) installs Composer dependencies into a
container-only `vendor` volume, regenerates the classmap, starts Apache for the smoke suite, runs PHPUnit, and
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
- **`UnconnectedMysqli::create()`** gives a real but unconnected `mysqli` for unit-testing methods of classes
  whose constructor type-hints `mysqli` but which never query (e.g. `TikTokOrder::getOrders()`).
- **PHP 7.3 syntax** applies to test code too (`composer run lint` covers `tests/`).
- **Host vs container**: the host may run a newer PHP (8.x). Deprecation notices are not failures; anything that
  behaves differently between 7.4 and 8.x (e.g. division by zero) belongs in the container run.

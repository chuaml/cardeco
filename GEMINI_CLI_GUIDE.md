# Gemini CLI Project Guide

This guide provides common commands and workflows for Gemini CLI when working on this project.
The authoritative reference is [AGENTS.md](AGENTS.md); the test harness is described in [tests/README.md](tests/README.md).

## Common Tasks

### Running Tests
Full suite on the real runtime (PHP 7.4 + a throwaway, seeded MySQL 8 – never touches the dev database):
```bash
composer run tests:docker                       # everything; first run builds the image
composer run tests:docker -- --testsuite unit   # any PHPUnit argument is passed through
composer run tests:docker -- --filter Golden
composer run tests:docker:down                  # tear the throwaway database down
```

Fast feedback without Docker (pure tests only, works on the host PHP):
```bash
composer run tests:unit
```

Inside the dev container (where `MYSQL_*` is set):
```bash
composer run tests            # all suites in phpunit.xml.dist
composer run tests:integration
composer run reload           # dump-autoload + tests
```

PHP 7.3 compatibility guard (production still runs 7.3):
```bash
composer run lint
```

Documented-but-unfixed bugs are tests in the `known-bug` group (excluded by default):
```bash
composer run tests:docker -- --group known-bug
```

### Debugging & Profiling
To run tests with Xdebug enabled for debugging:
```bash
composer run debug-tests
```

To profile tests with Xdebug:
```bash
composer run profile
```

### Development Server
To start the application in development mode (with Xdebug):
```bash
composer run dev
```

To start the application in standard mode:
```bash
composer run serve
```

## Project Structure Notes

- **Autoloading:** `App\` is PSR-4 (`src/`); everything else under `src/` (including `src/Legacy/`) and `tests/` is a Composer classmap. After adding or moving classes run `XDEBUG_MODE=off composer dump-autoload`.
- **Global Functions:** Core helper functions are located in `./src/global.function.php`.
- **Entry Point:** The primary entry point for all web requests is `router.php`; routing rules live in `src/Routing/RouteResolver.php`.
- **New pages:** implement `App\Handler\<Name>Handler` in `src/Handler/` (see `AbstractDailyOrderHandler` for the upload pattern); do not add new `request_handler/*.php` scripts.
- **Tests:** `tests/Unit`, `tests/Integration` (extend `test\Support\DatabaseTestCase`, fixtures prefixed `ZZTEST-`), `tests/Smoke`; PHPUnit 9.5, config in `phpunit.xml.dist`.

## Working with Git
- **Configuration Files:** Avoid modifying or committing files in `config.dev/`. They should be kept as `assume-unchanged` locally.
- **Branches:** Always target the `dev` branch for initial development before moving to `staging` or `main`.

# AGENTS.md — AI Coding Agent Guide for Cardeco

This document is the authoritative technical reference and operational guide for AI coding agents working on the **Cardeco** repository. Read and follow these instructions before modifying code, running commands, or debugging issues.

---

## 1. Project Overview & Domain Context

**Cardeco** is a **proprietary** internal e-commerce operations, inventory/stock management, platform fee reconciliation, and SQL Accounting integration web application (operating in `MYR` across **Lazada**, **Shopee**, **TikTok**, and **BigSeller**).

- **Security & Privacy Mandate**: This software is **PROPRIETARY** and **NOT FREE TO DISTRIBUTE**. Never expose proprietary business data, database dumps, or credentials outside the workspace.
- **Core Business Capabilities**:
  1. **Daily Order Processing & Pick-List Generation** (`/`, `/index`, `/lazada`, `/shopee`, `/tiktok`, `/bigseller`): Parses uploaded `.xlsx`/`.csv` platform order exports, matches platform SKUs (`seller_sku`, `bigseller_sku_map`) to internal `stock_items`, outputs **To Restock**, **To Collect** (printable pick list), **Not Found**, and **Orders** tables, and sends daily order telemetry to Google Analytics 4 (`js/daily-orders-to-gtag-ga4.js`).
  2. **Stock & Catalog Management** (`/liststock`, `/updateStock`, `/ItemManager`, `/itemdetail`, `/bookmarks`, `/bookmarks_modify`, `/image_export`, `/stockreport`, `/counting`, `/catalog`, `/LzdItemManager`): Searches/paginates inventory, imports tab-delimited `.txt` stock exports from SQL Accounting, manages item details and BigSeller SKU mappings, and generates printable/watermarked product catalogs.
  3. **Monthly Order Records & SQL Accounting Export** (`/RecordInserter`, `/RecordInserterCSV`, `/RecordEditor`, `/RecordUpdater`, `/Cashsale`, `/CashSaleSqlImport`, `/SkuManager`): Persists order records (`orders`, `orders_insert_log`, `orders_temp`), tracks stock-out dates by tracking number, manages SKU mappings, and converts monthly cash sales Excel workbooks into SQL Accounting `.xlsx` import files via `PhpSpreadsheet`.
  4. **Fee Statement Reconciliation & Reporting** (`/fee_statement`, `/lzd_fee_statement`, `/LzdFeeChecker`, `/ShippedItemReport`): Reconciles Lazada/Shopee fee statements and courier records against order totals and generates shipped item reports.

---

## 2. Dual-Environment Reality: WAMP Production vs. Docker LAMP Development

This project is mid-migration from a legacy Windows **WAMP** stack to a containerized Linux **LAMP** stack.

| Environment | Stack & OS | PHP Version | Database | How Configured / Started |
| :--- | :--- | :--- | :--- | :--- |
| **Live Production (`main`)** | **Windows WAMP64** (`\wamp64`), Apache 2.4 | **PHP 7.3** | MySQL 8 (`localhost`, user `root`, password `""`, DB `cardeco`) | Native Windows WAMP server; DB backup via `backup.database.bat`. |
| **Development (`dev`)** | **Docker LAMP** (`Dockerfile` target `dev_app`, Debian + Apache 2.4 + Xdebug 3.1.6) | **PHP 7.4** (`php:7.4-apache`) | MySQL 8.0.41 (`mysql-dev:3306`, host port `3308`, DB `cardeco_dev`) | `compose.yml` + `.devcontainer/devcontainer.json` (HTTP on host `127.0.0.1:8082`). |
| **Staging / Planned Prod Containers** | **Docker LAMP** (`Dockerfile` target `production_app`) | **PHP 7.4** (`php:7.4-apache`) | MySQL 8.0.41 (`mysql:3306`, DB `cardeco`) | `compose.staging.yml` (port `8081`) / `compose.production.yml` (port `8080`). Not yet fully cut over in live production. |

### Critical Cross-Environment Compatibility Rules

1. **Strict PHP 7.3 Syntax Compatibility**:
   - Even though the Docker container runs PHP 7.4 (and `.idx/dev.nix` specifies PHP 8.1), **live production still runs PHP 7.3 on WAMP**.
   - **DO NOT use PHP 7.4+ syntax**, or live production will suffer fatal parse errors:
     - **No typed class properties** (use `/** @var Type */ private $prop;` instead of `private Type $prop;`).
     - **No arrow functions** (use `function ($x) { return $x; }` instead of `fn($x) => $x`).
     - **No null-coalescing assignment operator** (`??=`).
     - **No trailing commas in function/method parameter lists**.
     - **No unpacking inside arrays with string keys or PHP 8+ features** (`match`, named arguments, union types, nullsafe `?->`, `str_contains()`, etc.).
2. **Linux Case-Sensitivity (LAMP) vs. Windows Case-Insensitivity (WAMP)**:
   - Windows NTFS ignores filename case, whereas Linux ext4/overlayfs in Docker is strictly case-sensitive.
   - Always ensure URL paths, `require`/`include` statements, AJAX endpoints, and PSR-4 class/file names match exact casing on disk.
3. **Cross-Platform File Paths**:
   - Never hardcode Windows backslashes (`\`) when parsing file paths or stream URIs. Always use `__DIR__`, `basename()`, `dirname()`, or `DIRECTORY_SEPARATOR`.

---

## 3. Request Lifecycle & 4-Tier Routing Architecture

All non-static requests are routed through `router.php` (enforced by `.htaccess` and `index.php`). Do **not** create new top-level PHP entry points.

### `.htaccess` Rewrite Rule
```apache
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ router.php [QSA,L]
```
- If the requested URL path matches an **existing physical file** (`-f`, such as `/ajax/orders/getOrdersByInsertLogId.php`, `/css/vibe.style.css`, `/js/function.js`), Apache serves/executes that file directly, **bypassing `router.php`**.
- Otherwise, Apache routes the request to `router.php`.

### `router.php` Execution Flow
1. Loads Composer autoloader (`require 'vendor/autoload.php'`) and registers a fallback PSR-4 autoloader for the `App\` namespace (`src/`).
2. Connects to MySQL by requiring `db/conn_staff.php`, which initializes `$con` (`mysqli`) and `$_isProduction` (`bool`).
3. Resolves `$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH)` with [`App\Routing\RouteResolver`](file:///home/mingliangc/cardeco/src/Routing/RouteResolver.php) (pure, unit-tested in `tests/Unit/Routing/RouteResolverTest.php`; paths containing `..` or NUL resolve to 404) across three handler tiers:

| Tier | Resolution Condition | Execution Contract | View Rendering |
| :--- | :--- | :--- | :--- |
| **Tier 1: Modern PSR-4 Handler** | `$path === '/'` (`App\Handler\LazadaHandler`) or `class_exists("App\\Handler\\{$className}Handler")` in `src/Handler/` | Instantiates `new $handlerClass($con)` and calls `$response = $handler->handle($_REQUEST, $_FILES)` returning `App\Handler\Response`. | `extract($response->data)`, sets `$_view_html_path = $response->viewPath`, and requires `view/_layout.main.html`. |
| **Tier 2: Procedural Request Handler** | `file_exists("request_handler{$path}.php")` (or `index.php` if directory) | Requires `request_handler/{path}.php` in `router.php` scope (has access to `$con`). Script sets `$_view_html_path = 'view/...'`. | If `$_view_html_path !== ''`, requires `view/_layout.main.html`. |
| **Tier 3: Legacy Page Fallback** | `file_exists("legacy_pages/request_handler{$path}.php")` | Sets `$_view_html_path = $legacy_page` directly. | Requires `view/_layout.main.html`, which includes `$legacy_page` inside `<body>` (script outputs HTML inline). |
| **Tier 4: Direct Endpoints (Bypasses `router.php`)** | Physical `.php` files in `ajax/**/*.php` or `process/**/*.php` | Executed directly by Apache when requested with `.php` extension (or included by a handler). Must require `db/conn_staff.php` and `vendor/autoload.php` themselves. | Returns raw JSON/HTML or redirects via `header('Location: ...')` / `<script>`. |

### Master Layout & Frontend Conventions (`view/`)
- [`view/_layout.main.html`](file:///home/mingliangc/cardeco/view/_layout.main.html) wraps all rendered pages:
  - `<head>`: Includes [`view/template/head.php`](file:///home/mingliangc/cardeco/view/template/head.php), which renders environment banners (`Staging - test server` / `test server: dev` when `$_isProduction !== true`), loads `css/vibe.style.css`, page transition loader (`#page-loader`), editable sheet component (`js/table-cd-editable-sheet/`), table sorter (`js/table-sorter/`), declarative AJAX form handler (`form[cd-ajax]`), and global legacy scripts (`js/jquery-3.3.1.min.js`, `js/myscript.js`, `js/function.js`).
  - `<body>`: Includes [`inc/html/nav.html`](file:///home/mingliangc/cardeco/inc/html/nav.html) followed by `require $_view_html_path;`.
- **Error Handling in `router.php`**:
  - Checks `error_get_last()` after handler execution; logs any non-Xdebug error and sets HTTP 500.
  - Catches `Exception\HttpException`: status `400` echoes message and exits; `404` includes `view/404.php`; `500` includes `view/500.php`.
  - Catches `Throwable`: sets HTTP 500 and includes `view/500.php`.

---

## 4. Directory Structure & Complete Route Map

### Directory Map
| Directory / File | Purpose |
| :--- | :--- |
| `router.php` / `index.php` | Front controller and single web entry point (`index.php` simply requires `router.php`). Tier resolution lives in `src/Routing/RouteResolver.php`. |
| `src/Routing/` | `App\Routing\RouteResolver` + `Route`: the Tier 1/2/3/404 precedence as a pure function (unit-tested). |
| `src/Handler/` | Modern PSR-4 request handlers under namespace `App\Handler`: `AbstractDailyOrderHandler` (shared upload -> pick-list flow) with `LazadaHandler`, `IndexHandler`, `BigsellerHandler`, `TiktokHandler`, `ShopeeHandler`; `ShippedItemReportHandler`; `Response`. |
| `src/Service/` | Business services shared by several pages: `App\Service\PickList\{PickListBuilder,PickListResult,PickListTables}` (keyed-SKU -> stock join -> To Restock / To Collect / Not Found) and `App\Service\Shipping\ShippingFeeByWeight`. |
| `src/OrderProcess/` | Platform order parsers (`OrderProcess\LazadaOrderProcess`, `BigSellerOrderProcess`, `TikTokOrder`, `ShopeeOrderProcess`) that turn an upload into the four pick-list tables via `src/Service/`, plus `Report\ShippedItemReport`. |
| `src/Exception/` | Custom exceptions (`Exception\HttpException`). |
| `src/require_core/` | Collection utilities (` LazyCollection`, `ICollection` under ` require_core\ArrayExtension` / `ArrayExtension`). |
| `src/Legacy/` | Legacy classes migrated from the former `inc/class/` directory (loaded via Composer `classmap` on `./src/`). Includes namespaces like `Orders\*`, `Product\*`, `Lazada\*`, `HTML\*`, `IO\*`, `Database\*`, and global-namespace classes (`StockManager`, `Bookmark`, `Database_Manager`, `Sql_Accounting_Export`, etc.). |
| `src/global.function.php` | Autoloaded global helpers: `text(?string $html)` (HTML-escape), `dumpjson($json)`, `dd(...$data)`, `ddtext(...$data)`. |
| `src/console.php` | Debugging helper `\console\dev` (`log`, `dump`, `dumpjson`). |
| `request_handler/` | Tier 2 procedural controllers mapping 1-to-1 to URL paths and setting `$_view_html_path`. |
| `legacy_pages/request_handler/` | Tier 3 legacy combined controller+view scripts rendered inside `view/_layout.main.html`. |
| `view/` | HTML and PHP view templates, `template/head.php`, `_layout.main.html`, `404.php`, `500.php`. |
| `process/` | Form-submission and action processing scripts (`process/*.php` and `process/RecordEditor/*.php`). |
| `ajax/` | Direct JSON AJAX endpoints (`ajax/orders/`, `ajax/stock_items/`, `ajax/seller_sku/`). |
| `inc/` | Shared navigation HTML (`inc/html/nav.html`) and legacy include (`inc/items_detail.php`). Note: `inc/class/` was moved to `src/Legacy/`. |
| `db/` | Database connection (`db/conn_staff.php`), initial seed dump (`db/cardeco.sql.gz`), and schema migrations (`db/upgrade.sql`). |
| `tests/` | PHPUnit 9.5 suites (`Unit/`, `Integration/`, `Smoke/`), shared support classes (`tests/Support/`, incl. `DatabaseTestCase`), golden files and sample `.xlsx`/`.csv`/`.json` fixtures - see `tests/README.md`. |
| `e2e/` | Playwright end-to-end browser tests (`e2e/example.spec.js`, configured in `playwright.config.js`). |
| `config.dev/` | Local PHP and Xdebug INI overrides (`php-dev-override.ini`, `xdebug.ini`) mounted read-only into the dev container. |

### Route-to-Handler-to-View Reference
| URL Route | Active Handler | View / Output | Key Domain Classes |
| :--- | :--- | :--- | :--- |
| `/` | `src/Handler/LazadaHandler.php` (Tier 1) | `view/lazada.php` | `OrderProcess\LazadaOrderProcess` |
| `/index`, `/lazada` | `src/Handler/IndexHandler.php` (extends `LazadaHandler`) / `LazadaHandler.php` (Tier 1) | `view/lazada.php` | `OrderProcess\LazadaOrderProcess` |
| `/shopee` | `src/Handler/ShopeeHandler.php` (Tier 1) | `view/shopee.html` | `OrderProcess\ShopeeOrderProcess`, `Orders\Factory\Shopee` |
| `/tiktok` | `src/Handler/TiktokHandler.php` (Tier 1) | `view/tiktok.html` | `OrderProcess\TikTokOrder` |
| `/bigseller` | `src/Handler/BigsellerHandler.php` (Tier 1) | `view/bigseller.html` | `OrderProcess\BigSellerOrderProcess` |
| `/ItemManager` | `request_handler/ItemManager.php` (Tier 2) | `view/ItemManager.html` | `Product\ItemManager`, `Product\ItemEditor`, `Product\Item` |
| `/itemdetail` | `request_handler/itemdetail.php` (Tier 2) | `view/itemdetail.html.php` | `Stock_Item` |
| `/RecordInserter` | `request_handler/RecordInserter.php` (Tier 2) | `view/RecordInserter.html` | `Orders\RecordInserter`, `Orders\Factory\Record` |
| `/RecordInserterCSV` | `request_handler/RecordInserterCSV.php` (Tier 2) | `view/RecordInserterCSV.html` | `Orders\RecordInserter`, `Orders\RecordDeleter` |
| `/RecordEditor` | `request_handler/RecordEditor.php` (Tier 2) | `view/RecordEditor.html` | `Orders\RecordEditor`, `Orders\MonthlyRecord`, `process/RecordEditor/*` |
| `/RecordUpdater` | `request_handler/RecordUpdater.php` (Tier 2) | `view/RecordUpdater.html` | `Orders\RecordUpdater`, `Orders\Factory\DateStockOut`, `Orders\StockOutTable` |
| `/SkuManager` | `request_handler/SkuManager.php` (Tier 2) | `view/SkuManager.html` | `Orders\SkuManager` |
| `/CashSaleSqlImport` | `request_handler/CashSaleSqlImport.php` (Tier 2) | `view/CashsaleSqlImport.html` | `Orders\Factory\Excel\CashSales`, `Orders\Factory\Excel\SqlImport` |
| `/ShippedItemReport` | `src/Handler/ShippedItemReportHandler.php` (Tier 1; the view calls `$handler->...()` while rendering, so `$con` must stay open) | `view/ShippedItemReport.html` | `Report\ShippedItemReport` |
| `/LzdFeeChecker` | `request_handler/LzdFeeChecker.php` (Tier 2) | `view/LzdFeeChecker.html` | `Lazada\Manager\Fee_StatementsManager`, `Lazada\Manager\OrdersManager` |
| `/LzdItemManager` | `request_handler/LzdItemManager.php` (Tier 2) | `view/LzdItemManager.html` | `Lazada\Factory\LzdItemFactory`, `Lazada\Manager\ItemManager` |
| `/liststock` | `legacy_pages/request_handler/liststock.php` (Tier 3) | Inline + `process/actionListstock` | `StockManager`, `Bookmark` |
| `/updateStock` | `legacy_pages/request_handler/updateStock.php` (Tier 3) | Inline + `process/updateStock` | `Sql_Accounting_Export`, `Database_Stock_Updater` |
| `/bookmarks`, `/bookmarks_modify` | `legacy_pages/request_handler/bookmarks*.php` (Tier 3) | Inline + `process/*Bookmark*` | `Bookmark`, `StockBookmark`, `Page_Handler_Bookmark` |
| `/image_export` | `legacy_pages/request_handler/image_export.php` (Tier 3) | Inline (`process/exportAll_StockImages.php`) | `Database_StockImages_Exporter` |
| `/counting` | `legacy_pages/request_handler/counting.php` (Tier 3) | Inline + `js/counting.js` | Client-side / stock counting utility |
| `/fee_statement`, `/lzd_fee_statement` | `legacy_pages/request_handler/*fee_statement.php` (Tier 3) | Inline + `process/*fee_statement` | `Courier_Record_Viewer`, `Lzd_Fee_Checker`, `Shopee_Fee_Statement` |
| `/stockreport` | `legacy_pages/request_handler/stockreport.php` (Tier 3) | Inline | `StockReport` |
| `/Cashsale` | `legacy_pages/request_handler/Cashsale.php` (Tier 3) | `view/Cashsale.html` | `Orders\Factory\Cashsale` |
| `/catalog` | `legacy_pages/request_handler/catalog.php` (Tier 3) | Inline | Queries `lzd_products` table directly |

---

## 5. Database Connection & Schema Conventions

### How `db/conn_staff.php` Connects
1. **WAMP Mode (`getenv('MYSQL_HOST') === false`)**:
   - Runs `exec('git branch --show-current 2>&1', $_output, $_result)`.
   - If the `git` command fails (exit code `!== 0`), it runs `git config --global --add safe.directory "{CONTEXT_DOCUMENT_ROOT}"` and throws an `Error`.
   - If branch is `main`, connects to DB `cardeco` (`$_isProduction = true`); on any other branch, connects to DB `cardeco_dev` (`$_isProduction = false`) on `localhost` (`root` / `""`).
2. **Docker / Linux Mode (`getenv('MYSQL_HOST') !== false`)**:
   - Reads environment variables `IS_PRODUCTION`, `MYSQL_HOST`, `MYSQL_USER`, `MYSQL_PASSWORD`, `MYSQL_DB`.
   - Enables strict MySQLi error reporting: `mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);`.
   - Assigns the `mysqli` connection to `$con`.

### Database Guidelines
- Inject `$con` (`mysqli`) into class constructors rather than using `global $con` inside classes.
- Be careful with `$con->close()` inside handlers: if a view template calls methods on a handler/model that queries `$con` during view rendering (such as `view/ShippedItemReport.html` or `view/itemdetail.html.php`), closing `$con` inside the request handler before the view renders will cause a fatal error.
- Schema changes must be recorded idempotently in [`db/upgrade.sql`](file:///home/mingliangc/cardeco/db/upgrade.sql) (e.g., `CREATE TABLE IF NOT EXISTS ...`).

---

## 6. Development, Autoloading & Testing Commands

### Xdebug & Composer CLI Rule
In the Docker dev environment, Xdebug is configured with `xdebug.start_with_request=yes` on port `9003`. Whenever no IDE debugger is actively listening on port `9003`, PHP CLI processes (including `composer` and `phpunit`) can hang or run slowly unless Xdebug is disabled.
- **Always prefix Composer or PHP CLI commands with `XDEBUG_MODE=off`:**
  ```bash
  XDEBUG_MODE=off composer dumpautoload --no-interaction
  ```

### Common Commands (`composer.json` & E2E)
```bash
# Regenerate Composer PSR-4 and classmap autoloader (required after adding/moving classes in src/ or tests/)
XDEBUG_MODE=off composer dumpautoload --no-interaction

# FULL test suite on the real runtime: PHP 7.4 + throwaway MySQL 8 seeded from db/cardeco.sql.gz (compose.test.yml).
# Never touches cardeco_dev. First run builds the image (~5 min). Extra args are passed to PHPUnit.
composer run tests:docker
composer run tests:docker -- --testsuite integration
composer run tests:docker -- --filter RecordInserter
composer run tests:docker -- --group known-bug      # documented bugs (expected to fail until fixed)
composer run tests:docker:down                       # remove the throwaway database

# Pure unit tests (no DB, no ext-zip) - also run on the host PHP
composer run tests:unit

# PHP 7.3 compatibility lint (PHPCompatibility via phpcs, config in phpcs.xml.dist)
composer run lint

# Inside the dev container (MYSQL_* env set): run the suites directly
composer run tests                 # everything in phpunit.xml.dist (known-bug group excluded)
composer run tests:integration
composer run tests:smoke           # needs APP_BASE_URL, e.g. APP_BASE_URL=http://localhost

# Reload Composer autoloader and run PHPUnit test suite
composer run reload

# Run PHPUnit tests with Xdebug enabled (for IDE step-debugging, trigger/idekey=vsc)
composer run debug-tests

# Profile PHPUnit tests with Xdebug
composer run profile

# Start Apache development server in foreground WITH Xdebug (develop,debug)
composer run dev

# Start Apache server in foreground in standard mode
composer run serve

# Run a single PHPUnit test file
php -d memory_limit=-1 -d xdebug.mode=Off ./vendor/bin/phpunit ./tests/lazada/LazadaTest.php --colors=always

# Run Playwright E2E tests (requires app server running on port 8082)
npx playwright test
```

> **Note on Running PHPUnit Tests**: see [`tests/README.md`](file:///home/mingliangc/cardeco/tests/README.md). Suites are defined in `phpunit.xml.dist`: `unit` (`tests/Unit/`), `integration` (`tests/Integration/` + the pre-existing `tests/{lazada,bigseller,tiktok,monthly_cashsales_record}/`) and `smoke` (`tests/Smoke/`, GETs every nav route over HTTP). DB tests extend [`test\Support\DatabaseTestCase`](file:///home/mingliangc/cardeco/tests/Support/DatabaseTestCase.php): one shared connection from `MYSQL_HOST/USER/PASSWORD/DB`, every test inside a rolled-back transaction, fixtures prefixed `ZZTEST-`, automatic skip when the env is missing and refusal to run against the production DB name. Tests tagged `@group known-bug` describe the *intended* behaviour of a documented bug and are excluded from the default run; fixing the bug = making the test pass and dropping the tag. Golden HTML of the four daily-order processors lives in `tests/Integration/OrderProcess/golden/` (`CARDECO_UPDATE_GOLDEN=1` regenerates - review the diff).

---

## 7. Coding Standards, Handler Pattern & Git Hygiene

### Coding Standards & Handler Pattern
- **Namespaces & Class Organization**:
  - `src/Handler/`: Request handlers / controllers (`namespace App\Handler;`).
  - `src/OrderProcess/`: Platform order processing engines and reports.
  - `src/Service/` & `src/Entity/`: Business logic services and data models for new features.
  - `src/Legacy/`: Legacy classes migrated from the former `inc/class/` directory.
- **Naming & Documentation**:
  - **Files & Classes**: Use `PascalCase` for class files (e.g., `LazadaHandler.php`, `StockManager.php`).
  - **Methods & Variables**: Use `camelCase` for methods (e.g., `handle()`); use `camelCase` or `snake_case` consistently within a file.
  - **PHPDoc**: Provide PHPDoc blocks for all public methods and properties for type safety and IDE support while maintaining **100% PHP 7.3 syntax compatibility**.
  - **HTML Escaping**: Escape untrusted output in views using the global `text($value)` helper from `src/global.function.php`.
- **Implementing New Page Handlers**:
  - Do not create new top-level PHP entry points; all page routes go through `router.php`.
  - Implement new page controllers as PSR-4 classes in `src/Handler/{PageName}Handler.php` under `namespace App\Handler;` (nested routes like `/my/new/page` resolve to `App\Handler\My\New\PageHandler` in `src/Handler/My/New/PageHandler.php`):
    ```php
    <?php

    namespace App\Handler;

    use mysqli;

    class MyNewHandler
    {
        /** @var mysqli */
        private $con;

        public function __construct(mysqli $con)
        {
            $this->con = $con;
        }

        public function handle(array $request, array $files): Response
        {
            $data = ['key' => 'value'];
            return new Response('view/my_new_view.php', $data);
        }
    }
    ```
- **Testing Mandate**:
  - Always check `tests/` for existing test cases when modifying core logic. New features or bug fixes should include corresponding PHPUnit tests in `tests/` (or Playwright tests in `e2e/`).
  - **Characterize before you change**: when touching legacy code, first lock its current output with a test (pure classes -> `tests/Unit/`, DB classes -> `tests/Integration/` on `DatabaseTestCase`, page output -> golden/smoke tests), then refactor, then run `composer run tests:docker` and `composer run lint`.
  - Page controllers no longer call `$con->close()`; `router.php` owns the connection and views may query during rendering.

### Git Branching & Protected Local Configs
- **Git Branching Model**:
  1. `dev` (or feature branches) for active development.
  2. `staging` for UAT verification and playground testing.
  3. `main` for live production.
- **Protected Local Config Files**:
  - Never stage or commit changes to `config.dev/php-dev-override.ini` or `config.dev/xdebug.ini`. They are kept `assume-unchanged` in Git:
    ```bash
    git update-index --assume-unchanged config.dev/php-dev-override.ini
    git update-index --assume-unchanged config.dev/xdebug.ini
    ```
- **Ignored / Excluded Paths for AI Agents**:
  - Avoid scanning, indexing, or modifying files in `vendor/`, `node_modules/`, `public/`, `upload/`, `db/dump/`, `db/dump-dev/`, `.git/`, or `config.dev/*.ini` unless explicitly instructed.

---

## 8. WAMP-to-LAMP Migration Notes & Production Upgrade Runbook

Keep the following repository details in mind when debugging, refactoring, or completing the Docker LAMP production migration:

1. **Safe Step-by-Step MyISAM-to-InnoDB Production Upgrade**:
   - Live WAMP production historically used `MyISAM` for 9 tables (`bookmarks`, `bookmarks_items`, `cashsales`, `courier_record`, `lzd_fee_statements`, `orders`, `orders_insert_log`, `orders_temp`, `seller_sku`).
   - [`db/upgrade.sql`](file:///home/mingliangc/cardeco/db/upgrade.sql) uses an idempotent stored procedure (`upgrade_myisam_to_innodb`) that queries `information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND ENGINE = 'MyISAM'` and only alters tables that are still on `MyISAM` (avoiding costly table rebuilds on subsequent runs).
   - **Production Upgrade Steps**:
     1. Deploy the latest PHP codebase (which includes `$con->commit()` in `request_handler/RecordInserter.php` and explicit column lists in `RecordInserter::commit()`, compatible with both `MyISAM` and `InnoDB`).
     2. Run `backup.database.bat` on WAMP to create `db/cardeco.bak.sql`.
     3. Execute `db/upgrade.sql` against the `cardeco` database during a maintenance window.
     4. Verify all tables report `ENGINE = 'InnoDB'` in `information_schema.TABLES`.

2. **Missing Legacy `_*.php` Partials in `process/`**:
   - `process/actionListstock.php`, `process/actionBookmarks.php`, `process/fee_statement.php`, and `process/lzd_fee_statement.php` include `_*.php` partial files (`_bookmarkListstock_items.php`, `_exportListstock_image.php`, `_printBookmarks_items.php`, `_removeBookmarks_items.php`, `_insertCourier_record.php`, etc.) that were historically excluded from Git. Check the WAMP production host for any untracked `process/_*.php` files before decommissioning the WAMP server.

3. **Persistent Volumes in Staging & Production Containers**:
   - Because `.dockerignore` excludes `public/` and `upload/` from the Docker image build context, `compose.staging.yml` and `compose.production.yml` bind-mount `./public:/var/www/html/public` and `./upload:/var/www/html/upload` into the `app` container. Ensure those directories exist and are writable by `www-data` on the Docker host.

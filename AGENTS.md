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
3. Resolves `$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH)` across three handler tiers:

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
| `router.php` / `index.php` | Front controller and single web entry point (`index.php` simply requires `router.php`). |
| `src/Handler/` | Modern PSR-4 request handlers under namespace `App\Handler` (`LazadaHandler.php`, `Response.php`). |
| `src/OrderProcess/` | Core order processing engines (`OrderProcess\LazadaOrderProcess`, `OrderProcess\BigSellerOrderProcess`, `OrderProcess\TikTokOrder`, `Report\ShippedItemReport`). |
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
| `tests/` | PHPUnit 9.5 test suite, test DB connector (`tests/db.connection.php`), and sample `.xlsx`/`.csv`/`.json` fixtures. |
| `e2e/` | Playwright end-to-end browser tests (`e2e/example.spec.js`, configured in `playwright.config.js`). |
| `config.dev/` | Local PHP and Xdebug INI overrides (`php-dev-override.ini`, `xdebug.ini`) mounted read-only into the dev container. |

### Route-to-Handler-to-View Reference
| URL Route | Active Handler | View / Output | Key Domain Classes |
| :--- | :--- | :--- | :--- |
| `/` | `src/Handler/LazadaHandler.php` (Tier 1) | `view/lazada.php` | `OrderProcess\LazadaOrderProcess` |
| `/index`, `/lazada` | `request_handler/index.php` -> `request_handler/lazada.php` (Tier 2, or Tier 1 if PSR-4 resolved) | `view/lazada.php` | `OrderProcess\LazadaOrderProcess` |
| `/shopee` | `request_handler/shopee.php` (Tier 2) | `view/shopee.html` | `Orders\Factory\Shopee`, `Product\Manager\ItemManager` |
| `/tiktok` | `request_handler/tiktok.php` (Tier 2) | `view/tiktok.html` | `OrderProcess\TikTokOrder` |
| `/bigseller` | `request_handler/bigseller.php` (Tier 2) | `view/bigseller.html` | `OrderProcess\BigSellerOrderProcess` |
| `/ItemManager` | `request_handler/ItemManager.php` (Tier 2) | `view/ItemManager.html` | `Product\ItemManager`, `Product\ItemEditor`, `Product\Item` |
| `/itemdetail` | `request_handler/itemdetail.php` (Tier 2) | `view/itemdetail.html.php` | `Stock_Item` |
| `/RecordInserter` | `request_handler/RecordInserter.php` (Tier 2) | `view/RecordInserter.html` | `Orders\RecordInserter`, `Orders\Factory\Record` |
| `/RecordInserterCSV` | `request_handler/RecordInserterCSV.php` (Tier 2) | `view/RecordInserterCSV.html` | `Orders\RecordInserter`, `Orders\RecordDeleter` |
| `/RecordEditor` | `request_handler/RecordEditor.php` (Tier 2) | `view/RecordEditor.html` | `Orders\RecordEditor`, `Orders\MonthlyRecord`, `process/RecordEditor/*` |
| `/RecordUpdater` | `request_handler/RecordUpdater.php` (Tier 2) | `view/RecordUpdater.html` | `Orders\RecordUpdater`, `Orders\Factory\DateStockOut`, `Orders\StockOutTable` |
| `/SkuManager` | `request_handler/SkuManager.php` (Tier 2) | `view/SkuManager.html` | `Orders\SkuManager` |
| `/CashSaleSqlImport` | `request_handler/CashSaleSqlImport.php` (Tier 2) | `view/CashsaleSqlImport.html` | `Orders\Factory\Excel\CashSales`, `Orders\Factory\Excel\SqlImport` |
| `/ShippedItemReport` | `request_handler/ShippedItemReport.php` (Tier 2) | `view/ShippedItemReport.html` | `Report\ShippedItemReport` |
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

# Run full PHPUnit 9.5 test suite (with Xdebug disabled for speed)
composer run tests

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

> **Note on Running PHPUnit Tests**: Tests in `tests/` obtain a database connection via [`tests/db.connection.php`](file:///home/mingliangc/cardeco/tests/db.connection.php), which reads `getenv('MYSQL_HOST')`, `getenv('MYSQL_USER')`, `getenv('MYSQL_PASSWORD')`, and `getenv('MYSQL_DB')`. Ensure those environment variables are set and the MySQL dev service (`mysql-dev` / port `3308`) is reachable when running DB-dependent tests.

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

## 8. Known Misconfigurations & WAMP-to-LAMP Migration Landmines

Keep the following existing repository issues in mind when debugging, refactoring, or completing the Docker LAMP production migration:

1. **Broken Hardcoded `inc/class/` Requires After Commit `f51a235`**:
   - Commit `f51a235` moved 77 legacy classes from `inc/class/` to `src/Legacy/` and removed `./inc/class/` from `composer.json`.
   - However, 10 files still contain hardcoded `require('inc/class/...')` or `require('../inc/class/...')` statements that will fail unless updated to use `vendor/autoload.php` or `src/Legacy/`:
     - `src/Legacy/Orders/Factory/Cashsale.php` (lines 6–9)
     - `process/RecordEditor/submit.php` (line 3)
     - `process/shopee.php` (lines 2–8)
     - `process/actionListstock.php` (lines 3–4)
     - `process/actionBookmarks.php` (lines 3–4)
     - `process/createBookmark.php` (line 3)
     - `process/exportAll_StockImages.php` (line 2)
     - `process/updateStock.php` (lines 4–5)
     - `process/updateBookmarks.php` (line 3)
     - `process/printCatalog.php` (line 3)
   - Additionally, if `vendor/composer/autoload_classmap.php` was not regenerated after pulling commit `f51a235`, it will still point to `/inc/class/...` until `XDEBUG_MODE=off composer dumpautoload` is executed.

2. **Case-Sensitivity Bugs Between Windows WAMP and Linux Docker LAMP**:
   - `js/RecordInserter.js` (line 25) requests `ajax/orders/getCountorderNum.php` (lowercase `o`), while the file on disk is `ajax/orders/getCountOrderNum.php` (uppercase `O`). This works on Windows WAMP (case-insensitive) but returns 404 on Linux Docker LAMP.
   - `router.php` (lines 48–51) resolves `App\Handler\{$className}Handler` using the raw URL casing from `$path` (e.g., `/lazada` -> `App\Handler\lazadaHandler`). On Linux, the fallback PSR-4 closure in `router.php` checks `file_exists('src/Handler/lazadaHandler.php')`, which fails to match `LazadaHandler.php` unless Composer's classmap has already been generated.
   - `request_handler/RecordInserter.php` (line 37) splits a temporary file path with `explode('\\', $file)` (Windows backslash) instead of `basename($file)`.

3. **Extensionless `process/*` Form Actions & Redirect Mismatches**:
   - Legacy pages in `legacy_pages/request_handler/` (`updateStock.php`, `liststock.php`, `bookmarks.php`, `bookmarks_modify.php`, `fee_statement.php`, `lzd_fee_statement.php`) submit forms to `action="process/..."` **without `.php`**. Without Apache `Options +MultiViews`, `.htaccess` sees `!-f` and rewrites `/process/...` to `router.php`, which throws a 404 because `router.php` only looks in `request_handler/` and `legacy_pages/request_handler/`.
   - Conversely, `request_handler/LzdFeeChecker.php` (line 39) redirects to `header('Location: fee_statement.php')` (with `.php`), which 404s through `router.php` because the route is `/fee_statement` (`legacy_pages/request_handler/fee_statement.php`).
   - `process/actionListstock.php` and `process/actionBookmarks.php` include `_bookmarkListstock_items.php`, `_exportListstock_image.php`, `_printBookmarks_items.php`, and `_removeBookmarks_items.php`, which are missing from `process/`.

4. **Docker & DevContainer Configuration Quirks**:
   - `.devcontainer/devcontainer.json` specifies `"service": "app-dev"`, which in `compose.yml` is the Playwright container (`mcr.microsoft.com/playwright:v1.59.1-noble`, lacking PHP/Apache/Composer), whereas the PHP/Apache container service in `compose.yml` is named `"app"`.
   - `compose.yml` (line 66) has a typo in `post_start`: `DEBUG_MODE=off composer dumpautoload --no-interaction && apache2-foreground` (`DEBUG_MODE` instead of `XDEBUG_MODE`, and starts a duplicate `apache2-foreground` process).
   - `Dockerfile` (line 50) declares `EXPOSE 8080`, whereas Apache inside the container listens on port `80` (mapped in `compose*.yml` as `8082:80`, `8081:80`, and `8080:80`).
   - `db/conn_staff.php` defines the `IS_PRODUCTION` constant only in the Docker branch (line 46) and omits `define('IS_PRODUCTION', $_isProduction)` before returning in the WAMP branch (line 38).

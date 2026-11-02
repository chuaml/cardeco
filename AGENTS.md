# AGENTS.md — CarDeco Project Guide

This document provides architectural context, runtime environment details, coding conventions, and operational guardrails for AI coding agents and developers working on the **CarDeco** codebase.

---

## 1. Project Overview & Business Domain

**CarDeco** is an internal e-commerce operations, warehouse inventory/stock management, and accounting export web application for an automotive accessories business selling across **Lazada**, **Shopee**, **TikTok**, and **BigSeller**, integrating with **SQL Accounting**.

### Core Functional Modules
- **Daily Order Processing & Packing Lists** (`/lazada`, `/shopee`, `/tiktok`, `/bigseller`):
  - Parses exported platform order spreadsheets/CSVs (`.xlsx` via `PhpOffice\PhpSpreadsheet` or `.csv` via `IO\CSVInputStream`).
  - Matches order SKUs against warehouse stock (`stock_items`, `stock`, `bigseller_sku_map`) and categorizes items into:
    - `toRestock` (`order quantity >= stock`)
    - `toCollect` (`order quantity <= stock`)
    - `notFound` (SKU not mapped/found in `stock_items`)
    - `orders` (detailed order line table with shipping weight/fee calculations)
- **Shipped Item Report** (`/ShippedItemReport`):
  - Processes BigSeller "all status" order exports (`Report\ShippedItemReport`) to generate daily stock-out item summaries and monthly shipped order records.
- **Stock & Inventory Management** (`/liststock`, `/updateStock`, `/ItemManager`, `/bookmarks`, `/image_export`, `/catalog`, `/stockreport`):
  - Search and paginate inventory (`StockManager`), bulk-update stock quantities (`Database_Stock_Updater`), edit item descriptions and BigSeller SKU mappings (`Product\ItemManager`, `Product\ItemEditor`), manage item bookmarks (`Bookmark`), and export product images/catalogs.
- **Order Lifecycle & Monthly Records** (`/RecordInserter`, `/RecordInserterCSV`, `/RecordEditor`, `/RecordUpdater`, `/Cashsale`, `/SkuManager`):
  - Insert, edit, delete, and reconcile order records (`orders`, `orders_temp`, `orders_insert_log`, `cashsales`, `courier_record`) and manage seller SKU to `item_code` mappings (`seller_sku`).
- **Accounting & Fee Statement Tools** (`/CashSaleSqlImport`, `/fee_statement`, `/lzd_fee_statement`, `/LzdFeeChecker`):
  - Transforms monthly cash sales Excel workbooks (`Orders\Factory\Excel\CashSales`) into formatted `.xlsx` spreadsheets (`Orders\Factory\Excel\SqlImport`) ready for import into **SQL Accounting**, including customer codes, payment methods, shipping fee lines, and platform/bank fee charges (`Orders\PaymentCharges\*`).

---

## 2. Hybrid Runtime Architecture (WAMP Production vs. Docker Non-Production)

> [!IMPORTANT]
> **Production runs natively on Windows WAMP (`wamp64`)**, whereas **Docker containers are used ONLY for staging and development/non-production environments**.
> Although `compose.production.yml` and `setup.production.bat` exist in the repository from containerization experiments, the live production server is a Windows WAMP stack. Every code change must remain 100% compatible with both Windows WAMP (production) and Linux Docker containers (staging/dev).

### Environment Matrix

| Environment | Runtime / Host | Web Server & Port | PHP Version | Database Host & Name | `IS_PRODUCTION` / `$_isProduction` | Config / Entry Script |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| **Production (Active)** | Windows Host (**WAMPServer** `wamp64`) | Apache 2.4 | **PHP 7.4** (`7.4.26`) | `localhost:3306` / `cardeco` (when git branch is `main`) | `$_isProduction = true` | `db/conn_staff.php` (`getenv('MYSQL_HOST') === false` branch) |
| **Local WAMP Dev** | Windows Host (**WAMPServer** `wamp64`) | Apache 2.4 | **PHP 7.4** | `localhost:3306` / `cardeco_dev` (when git branch $\neq$ `main`) | `$_isProduction = false` | `db/conn_staff.php` (`getenv('MYSQL_HOST') === false` branch) |
| **Staging (Container)** | Docker (`cardeco:staging` from `production_app` target) | Apache (`127.0.0.1:8081->80`) | **PHP 7.4** (`php:7.4-apache`) | `mysql:3306` (host `3307`) / `cardeco` (`mysql:8.0.41`) | `IS_PRODUCTION=0` (`false`) | `compose.staging.yml` / `setup.staging.bat` |
| **Dev (Container)** | Docker / VS Code Dev Container (`dev_app` target) | Apache (`8082->80`) + Xdebug 3.1.6 | **PHP 7.4** (`php:7.4-apache`) | `mysql-dev:3306` (host `3308`) / `cardeco_dev` (`mysql:8.0.41`) | `IS_PRODUCTION=0` (`false`) | `compose.yml` + `.devcontainer/devcontainer.json` |

### How `db/conn_staff.php` Detects the Environment
1. **WAMP Mode (`getenv('MYSQL_HOST') === false`)**:
   - Assumes Windows WAMP host.
   - Runs `exec('git branch --show-current 2>&1')`. (If git fails due to directory ownership, it runs `git config --global --add safe.directory ...` and throws an `Error`).
   - If `_CURRENT_BRANCH_NAME === 'main'`, sets `$_isProduction = true` and `DB_NAME = 'cardeco'`. Otherwise sets `$_isProduction = false` and `DB_NAME = 'cardeco_dev'`.
   - Connects via `mysqli_connect('localhost', 'root', '', DB_NAME)` and returns `$con`.
2. **Container Mode (`getenv('MYSQL_HOST') !== false`)**:
   - Reads `IS_PRODUCTION`, `MYSQL_HOST`, `MYSQL_USER`, `MYSQL_PASSWORD`, and `MYSQL_DB` from container environment variables.
   - Connects via `mysqli_connect(...)` with `MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT` enabled.

---

## 3. Request Lifecycle & Routing Architecture

```
HTTP Request
  │
  ├─► Matches existing file/dir on disk? (e.g. /css/*, /js/*, /ajax/*.php, /process/*.php)
  │     └── YES ──► Served/executed directly by Apache (bypasses router.php)
  │
  └── NO ( RewriteCond %{REQUEST_FILENAME} !-f && !-d in .htaccess )
        └── Rewritten to router.php [QSA,L]
              ├── Loads vendor/autoload.php
              ├── Loads db/conn_staff.php (initializes $con and $_isProduction)
              ├── Maps "/" ──► request_handler/lazada.php
              ├── Maps "/<path>" ──► request_handler/<path>.php (or index.php if dir)
              ├── Checks error_get_last() after execution
              └── Catches Exception\HttpException (400/404/500) & Throwable (500)
                    └── Renders view/404.php or view/500.php
```

### Three Types of PHP Entry Points
1. **Routed Controllers (`request_handler/*.php`)**:
   - Executed via `router.php` when visiting `/<PageName>` (e.g., `/bigseller` $\rightarrow$ `request_handler/bigseller.php`).
   - Current working directory (CWD) is the **repository root**.
   - `vendor/autoload.php` and `db/conn_staff.php` (`$con`, `$_isProduction`) are **already loaded** by `router.php`—do not re-require them inside `request_handler/*.php`.
   - Controllers should close `$con` in a `finally` block (`try { ... } finally { $con->close(); }`) and render a template from `view/`.
2. **Direct AJAX Endpoints (`ajax/**/*.php`)**:
   - Called directly from frontend JavaScript (e.g., `ajax/orders/getCountOrderNum.php`, `ajax/seller_sku/getItemCodeBySku.php`, `ajax/stock_items/getItemCode.php`).
   - Bypass `router.php`; must explicitly load `require_once(__DIR__ . '/../../db/conn_staff.php')` (and `vendor/autoload.php` if autoloaded classes are needed) and close `$con`.
3. **Process Scripts (`process/**/*.php`)**:
   - **Included sub-scripts** (e.g., `process/RecordEditor/submit.php`, `process/RecordEditor/exportCSV.php`, `process/exportAll_StockImages.php`): required from inside a `request_handler/*.php` controller; CWD is the repository root.
   - **Standalone legacy form handlers** (e.g., `process/actionListstock.php`, `process/updateStock.php`, `process/fee_statement.php`, `process/lzd_fee_statement.php`): posted to directly from HTML forms; CWD is `process/`, so they use `require_once('../db/conn_staff.php')`.

---

## 4. Directory Structure & Code Organization

```text
/home/mingliangc/cardeco
├── .devcontainer/          # VS Code Dev Container config (uses ../compose.yml, service: app-dev)
├── .vscode/                # VS Code tasks, Xdebug launch configs, and workspace settings
├── ajax/                   # Direct AJAX endpoints (orders/, seller_sku/, stock_items/)
├── config.dev/             # Dev PHP & Xdebug ini overrides (php-dev-override.ini, xdebug.ini)
├── css/                    # Stylesheets (style.css, catalog*.css)
├── db/                     # Database connection & schema files
│   ├── conn_staff.php      # Hybrid WAMP / Docker MySQL connection script ($con)
│   ├── cardeco.sql.gz      # Compressed initial database seed for Docker containers
│   └── upgrade.sql         # Idempotent schema migrations applied on container startup
├── dev.tool/               # Windows batch helper scripts for Docker & Xdebug CLI runs
├── inc/                    # Core/legacy includes, classes, and navigation HTML
│   ├── class/              # Domain models, factories, managers, HTML & Excel utilities
│   │   ├── Database/       # Deletable, Insertable, Updatable interfaces
│   │   ├── HTML/           # HtmlTable, HtmlTableRow, HtmlTableCell, TableDisplayer, EscapableData
│   │   ├── IO/             # CSVInputStream, FileInputStream
│   │   ├── Lazada/         # Lazada Item, FeeStatement, Factory/*, Manager/*
│   │   ├── Orders/         # Record, MonthlyRecord, Factory/Excel/*, PaymentCharges/*, SkuManager
│   │   ├── Product/        # Item, ItemEditor, ItemManager, Factory/*, Manager/ItemManager
│   │   └── *.php           # Legacy root classes (StockManager, Bookmark, Database_Manager, etc.)
│   ├── html/nav.html       # Global top navigation bar included across views
│   └── items_detail.php
├── js/                     # Frontend JS (jQuery 3.3.1, page scripts, table-sorter, table-cd-editable-sheet)
├── process/                # Form processing & CSV/image export scripts
├── public/                 # Static public assets
├── request_handler/        # Page controllers routed by router.php
├── src/                    # Modernized PHP classes & global helper functions
│   ├── Exception/          # HttpException.php
│   ├── OrderProcess/       # BigSellerOrderProcess, LazadaOrderProcess, TikTokOrder, ShippedItemReport
│   ├── require_core/       # ArrayExtension\ICollection, LazyCollection
│   ├── console.php         # \console\dev::dumpjson() / dump()
│   └── global.function.php # Global helpers: text(), dumpjson(), dd(), ddtext()
├── tests/                  # PHPUnit 9.5 test suites & sample fixture files (.xlsx, .csv, .json, .html)
│   ├── db.connection.php   # Test database connection helper (reads MYSQL_* env vars)
│   └── Mock.php            # \test\Mock::newFile() helper for mocking $_FILES
├── upload/                 # Uploaded product images (upload/img/*)
├── view/                   # HTML/PHP views (404.php, 500.php, *.html, template/head.php)
├── .htaccess               # Apache rewrite rules to router.php & static asset caching
├── compose.yml             # Docker Compose for development (mysql-dev + app-dev)
├── compose.staging.yml     # Docker Compose for staging (mysql + app on port 8081)
├── compose.production.yml  # Docker Compose production template (inactive; prod uses WAMP)
├── composer.json           # Composer config (phpoffice/phpspreadsheet ^1.29, phpunit 9.5)
├── Dockerfile              # Multi-stage PHP 7.4 Apache Dockerfile (base_image, production_app, dev_app)
└── router.php              # Front controller for virtual routes
```

---

## 5. Database Schema & Conventions

### Core Tables
- **Inventory & Products**:
  - `stock_items` (`id`, `item_code`, `description`, `uom`, ...) — master product catalog.
  - `stock` (`id`, `quantity`, ...) — current stock quantities (joined 1:1 with `stock_items.id`).
  - `stock_images` — product image metadata referencing files in `upload/img/`.
  - `bigseller_sku_map` (`id`, `bigseller_sku`, `item_id`, `date_created`, `date_modified`) — maps BigSeller SKUs to `stock_items.id`.
  - `seller_sku` (`sku`, `itemCode`) — maps platform seller SKUs to `stock_items.item_code`.
  - `lzd_products` — Lazada product catalog and shipping weight data (`AutoFilling::setShippingWeightByLzdProduct`).
  - `bookmarks`, `bookmarks_items` — saved item lists for quick stock checking/export.
- **Orders & Accounting**:
  - `orders`, `orders_temp`, `orders_insert_log` — imported marketplace order records and batch upload logs.
  - `cashsales` — monthly cash sales records.
  - `courier_record` — shipping/courier fee records.
  - `lzd_fee_statements` — Lazada fee statement entries.

### Database Rules
1. **Prepared Statements**: Always use parameterized `mysqli` prepared statements (`$con->prepare(...)` and `$stmt->bind_param(...)`) as demonstrated in [`inc/class/Product/Manager/ItemManager.php`](file:///home/mingliangc/cardeco/inc/class/Product/Manager/ItemManager.php). Avoid raw string concatenation in SQL queries.
2. **Schema Migrations**: Any new table or schema alteration must be written idempotently (e.g., `CREATE TABLE IF NOT EXISTS ...`) in [`db/upgrade.sql`](file:///home/mingliangc/cardeco/db/upgrade.sql). Docker containers execute `db/upgrade.sql` on startup; on WAMP production, apply changes from `db/upgrade.sql` to MySQL.

---

## 6. Critical Coding Rules & Gotchas

### 1. Strict PHP 7.4 Compatibility
Production runs **PHP 7.4**. Do **not** use PHP 8.0+ syntax or standard library functions, even if your local/IDE environment has PHP 8.x installed (note that `.idx/dev.nix` references `php81`, while `Dockerfile` and WAMP use `php7.4`):
- **Forbidden (PHP 8.0+)**:
  - Constructor property promotion (`public function __construct(private mysqli $con)`)
  - Union types (`int|float`, `string|false`) or `mixed` / `never` types
  - `match (...) { ... }` expressions
  - Named arguments (`htmlspecialchars($str, double_encode: false)`)
  - Nullsafe operator (`$obj?->method()`)
  - `readonly` properties or classes
  - `str_contains()`, `str_starts_with()`, `str_ends_with()`, `array_is_list()`
- **Allowed (PHP 7.4)**:
  - Typed properties (`private mysqli $con;`, `private ?ExcelReader $xlsx = null;`)
  - Arrow functions (`fn($x) => ...`)
  - Null coalescing assignment (`$a ??= $b`)
  - Array spread operator (`[...$list]`)
  - Parameter and return type declarations (`?string`, `array`, `void`, `bool`, `int`, `float`)

### 2. Composer `classmap` Autoloading Requires `composer dumpautoload`
Look at [`composer.json`](file:///home/mingliangc/cardeco/composer.json):
```json
"autoload": {
    "classmap": ["./inc/class/", "./src/"],
    "files": ["./src/global.function.php"]
},
"autoload-dev": {
    "classmap": ["./tests/"]
}
```
Because this project uses **classmap** autoloading (not PSR-4):
- Class namespaces do not strictly mirror directory paths (e.g., `src/OrderProcess/ShippedItemReport.php` declares `namespace Report;`, and `src/OrderProcess/BigSeller/BigSellerOrderProcess.php` declares `namespace OrderProcess;`).
- **Whenever you create, rename, or move any PHP class or test file, you MUST run `composer dumpautoload`** before running tests or serving requests, otherwise PHP will fail with `Class not found`.

### 3. Windows (WAMP) vs. Linux (Docker) Case-Sensitivity & Path Quirks
- **Filesystem Case Sensitivity**: Windows NTFS is case-insensitive, whereas Linux Docker containers are case-sensitive. Always match the exact uppercase/lowercase filename when writing `require`, `include`, URLs, and AJAX paths.
  - *Known legacy quirk*: [`js/RecordInserter.js`](file:///home/mingliangc/cardeco/js/RecordInserter.js#L25) references `ajax/orders/getCountorderNum.php` while the file on disk is [`ajax/orders/getCountOrderNum.php`](file:///home/mingliangc/cardeco/ajax/orders/getCountOrderNum.php).
- **Legacy Form `action="process/..."` Attributes**:
  - Several older templates/handlers (e.g., `liststock.php`, `updateStock.php`, `bookmarks.php`, `fee_statement.php`) submit to `action="process/actionListstock"` without `.php`. On Apache setups without `MultiViews`, `.htaccess` rewrites non-existent paths to `router.php`, which prepends `request_handler/`.
  - For all **new** features, follow the modern pattern used in `RecordEditor.php`, `ItemManager.php`, `BigSeller`, and `ShippedItemReport`: submit forms directly to the `/<HandlerName>` route in `request_handler/<HandlerName>.php` (or use `form[cd-ajax]` defined in `view/template/head.php`).

### 4. Error Handling & Global Helpers
- `router.php` checks `error_get_last()` after running a request handler and converts any uninhibited PHP warning/notice into a `500 Internal Server Error` (`RuntimeException`). Ensure variables and array keys are checked with `isset()` / `array_key_exists()` / `??` to prevent notices.
- Throw `new \Exception\HttpException(400, 'message')` or `new \Exception\HttpException(404, 'message')` in routed handlers when returning HTTP 400/404 responses.
- Global helper functions in [`src/global.function.php`](file:///home/mingliangc/cardeco/src/global.function.php) are always available:
  - `text(?string $html): string` — UTF-8 `htmlspecialchars` wrapper for safe HTML escaping.
  - `dumpjson($json)` — sends JSON headers and exits with `json_encode`.
  - `dd(...$data)` / `ddtext(...$data)` — dumps variables and exits immediately.

### 5. Frontend Conventions (`view/template/head.php`)
- Include `<?php require('view/template/head.php') ?>` inside `<head>` and `<?php include('inc/html/nav.html'); ?>` at the top of `<body>`.
- `view/template/head.php` automatically provides:
  - Green **"Staging - test server"** banner when `$_isProduction !== true`.
  - Smooth page-transition loader (`#page-loader`).
  - Automatic table sorting (`js/table-sorter/table-sorter-init.js`).
  - Custom AJAX form attribute `form[cd-ajax]`: any `<form cd-ajax>` is intercepted via `fetch()` (`application/x-www-form-urlencoded`) and dispatches custom DOM events (`submitted`, `submitted-failed`, `not-submitted`).
  - Editable spreadsheet table component in `js/table-cd-editable-sheet/`.

---

## 7. Development, Testing & Deployment Commands

### Running Tests (PHPUnit 9.5)
Tests are located in [`tests/`](file:///home/mingliangc/cardeco/tests) and require environment variables (`MYSQL_HOST`, `MYSQL_USER`, `MYSQL_PASSWORD`, `MYSQL_DB`) for tests that call `require 'tests/db.connection.php'`.

```bash
# Regenerate classmap autoloader (required after adding/moving classes)
composer dumpautoload

# Run PHPUnit test suite (with Xdebug disabled for speed)
composer run tests

# Regenerate autoloader and run PHPUnit test suite
composer run reload

# Run a single PHPUnit test file
./vendor/bin/phpunit ./tests/bigseller/BigSellerTest.php --colors=always

# Run tests with Xdebug step-debugging enabled (port 9003, idekey=vsc)
composer run debug-tests
```

### Docker Environment Commands (Dev & Staging)
```bash
# Start development container stack (MySQL on :3308, App on :8082)
docker compose --file compose.yml -p cardeco_dev up -d

# Inside the dev container, start Apache in the foreground (after dumpautoload)
composer run serve

# Build and deploy staging container stack (MySQL on :3307, App on 127.0.0.1:8081)
docker build --target production_app -t cardeco:staging .
docker compose -p cardeco_staging down
docker compose --file compose.staging.yml -p cardeco_staging up -d
```

### Windows WAMP Scripts (Production & Staging Host)
- [`backup.database.bat`](file:///home/mingliangc/cardeco/backup.database.bat): Locates `\wamp64\bin\mysql\mysql8*\bin\mysqldump.exe` and dumps the `cardeco` database to `db\cardeco.bak.sql`.
- [`version.update.staging.bat`](file:///home/mingliangc/cardeco/version.update.staging.bat): Runs `git stash && git fetch --all && git pull` and invokes `setup.staging.bat`.

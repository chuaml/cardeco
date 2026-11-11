# Project Architecture

This document describes the architectural patterns and directory structure of the project.
The authoritative, more detailed reference for AI agents is [AGENTS.md](AGENTS.md).

## 1. Request Lifecycle

The application follows a custom Front Controller pattern, with all requests routed through `router.php`
(enforced by `.htaccess`; physical files such as `ajax/**/*.php`, `process/**/*.php`, CSS and JS are served directly).

### **General Flow:**
1.  **Entry Point:** `router.php` initializes the environment (Composer autoloading, database connection `$con`).
2.  **Routing:** `App\Routing\RouteResolver` maps the request path to one of three tiers, in this order:
    - **Tier 1 – handler class:** `App\Handler\{Ucfirst segments}Handler` in `src/Handler/`
      (`/bigseller` -> `BigsellerHandler`, `/my/new/page` -> `My\New\PageHandler`).
    - **Tier 2 – procedural controller:** `request_handler/{path}.php`.
    - **Tier 3 – legacy page:** `legacy_pages/request_handler/{path}.php` (controller and view in one file).
    - Otherwise `404`. Paths containing `..` never reach the filesystem.
3.  **Handler Execution:**
    - Tier 1: `$handler->handle($_REQUEST, $_FILES)` returns an `App\Handler\Response` (`viewPath` + `data`);
      the data array is `extract()`ed into the view scope.
    - Tier 2: the script runs inside `router.php`'s scope (sees `$con`) and sets `$_view_html_path`.
4.  **View Rendering:**
    - If a view path is set, `router.php` includes `view/_layout.main.html`.
    - `_layout.main.html` wraps the content with common headers (`view/template/head.php`), navigation
      (`inc/html/nav.html`), and then includes the specific view file.
    - **`router.php` owns the database connection.** Controllers must not call `$con->close()`; some views
      (`ShippedItemReport`, `itemdetail`) query while rendering.

### Daily-order pages (Lazada / BigSeller / TikTok / Shopee)

```
upload  ->  App\Handler\<Platform>Handler (AbstractDailyOrderHandler)
        ->  OrderProcess\<Platform>OrderProcess::getOrders()   one array per unit sold, read by column position
        ->  App\Service\PickList\PickListBuilder::build()      group by SKU, join stock_items/stock, back-fill description + stock
        ->  PickListResult::toRestock()/toCollect()/notFound   + PickListTables (4-column) or BigSeller's per-marketplace table
        ->  App\Service\Shipping\ShippingFeeByWeight::apply()  weight-based fee estimate from lzd_products (Lazada, BigSeller)
        ->  view/<platform>.{php,html}                          Data (4 tables), msg, jsonOrders, dailyOrderFile_Sha1Hash
```

## 2. Directory Structure

| Directory | Purpose |
| :--- | :--- |
| `router.php` | Main entry point and router. |
| `src/Routing/` | `RouteResolver` / `Route`: the routing rules as a pure, unit-tested class. |
| `src/Handler/` | Tier-1 page controllers (`namespace App\Handler`). |
| `src/Service/` | Business services shared across pages (`App\Service\PickList`, `App\Service\Shipping`). |
| `src/OrderProcess/` | Platform order-file parsers and reports (`OrderProcess\*`, `Report\ShippedItemReport`). |
| `src/Legacy/` | Legacy classes migrated from the former `inc/class/` (namespaces `Orders\*`, `Product\*`, `Lazada\*`, `HTML\*`, `IO\*`, `Database\*` and global-namespace classes such as `StockManager`). Loaded via the Composer classmap. |
| `src/Exception/`, `src/require_core/`, `src/global.function.php` | `HttpException`, `LazyCollection`, global helpers (`text()`). |
| `request_handler/` | Tier-2 controllers that handle page-level requests and map to views. |
| `legacy_pages/` | Tier-3 pages being slowly migrated. |
| `ajax/` | Endpoints specifically for AJAX requests (served directly by Apache). |
| `process/` | Backend processing scripts, often for form submissions (served directly by Apache). |
| `view/` | HTML/PHP templates for the user interface. |
| `db/` | Database connection logic (`conn_staff.php`), seed dump and idempotent migrations (`upgrade.sql`). |
| `js/` / `css/` | Frontend assets. |
| `tests/` | PHPUnit suites (`Unit`, `Integration`, `Smoke`) and support classes – see [tests/README.md](tests/README.md). |
| `compose.test.yml` | Throwaway test stack (PHP 7.4 + seeded MySQL 8) used by `composer run tests:docker`. |

## 3. Key Components & Conventions

### **Database**
- The global variable `$con` (`mysqli`) is initialized in `db/conn_staff.php` and injected into classes via constructors.
- Prepared statements for all new code; the `Stock*`/`Bookmark*`/`catalog` family still uses escaped string SQL.

### **Class Loading**
- **Modern:** classes in `src/` use namespaces (`App\...` is PSR-4; `OrderProcess\`, `Orders\`, ... are classmap).
- **Legacy:** classes in `src/Legacy/` are loaded through the Composer classmap – run `XDEBUG_MODE=off composer dumpautoload` after adding or moving a class.

### **Naming Conventions**
- **Files:** PascalCase for classes (`StockManager.php`), snake_case for some legacy handlers/views.
- **Classes:** PascalCase.
- **Methods/Variables:** camelCase (modern) or snake_case (legacy).
- **PHP version:** everything must stay PHP 7.3-compatible (`composer run lint`).

## 4. Testing Strategy

- **Characterize first:** legacy behaviour is locked by tests before it is changed. Pure classes get unit tests
  (`tests/Unit`), DB classes get seeded, transaction-isolated integration tests (`tests/Integration`), page output is
  protected by golden HTML files and an HTTP smoke test over every navigation route.
- **`@group known-bug`:** a failing-by-design test that states the intended behaviour of a documented bug; excluded
  from the default run until the bug is fixed.

## 5. Documentation Strategy

- **`GEMINI.md`**: Context-specific instructions for AI agents in specific directories.
- **PHPDoc**: All new code must include PHPDoc blocks for type safety and IDE support.
- **API Documentation**: AJAX and form submission endpoints should be documented in a future `API.md`.

# Contributing to the Project

This project is undergoing a migration from a legacy PHP script-based architecture to a modern, class-based PSR-4 architecture.

## 1. Coding Standards

- **Namespaces:** Use the `App\` namespace for all new classes in `src/`.
- **File Names:** Use PascalCase for class files (e.g., `LazadaHandler.php`).
- **Method Names:** Use camelCase (e.g., `handle()`).
- **Variable Names:** Use camelCase or snake_case consistently within a file.
- **PHPDoc:** Provide PHPDoc blocks for all public methods and properties.

## 2. Handler Pattern

New page handlers should be implemented as classes in `src/Handler/`.

### **Example Handler Structure:**
```php
namespace App\Handler;

use mysqli;
use App\Handler\Response;

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
        // Business logic here
        $data = ['key' => 'value'];
        return new Response('view/my_new_view.html', $data);
    }
}
```
Upload-driven pages that produce the four pick-list tables should extend `App\Handler\AbstractDailyOrderHandler`
(see `BigsellerHandler` – three one-line methods).

### **Routing:**
The `router.php` automatically attempts to resolve paths to classes:
- URL `/my/new/page` -> `App\Handler\My\New\PageHandler` in `src/Handler/My/New/PageHandler.php`.

## 3. Class Organization

- **`src/Handler/`**: Request handlers (Controllers).
- **`src/Service/`**: Business logic and complex operations.
- **`src/Legacy/`**: Classes migrated from `inc/class/` but not yet fully refactored.
- **`src/Entity/`**: Data models (if applicable).

## 4. Database Access

Use the global `$con` (mysqli) injected into the handler's constructor. Avoid using `global $con` inside classes; prefer dependency injection. Never call `$con->close()` from a handler or controller: `router.php` owns the connection and views may query while rendering.

## 5. Tests First

- Lock existing behaviour before changing it: pure classes -> `tests/Unit/`, database classes -> `tests/Integration/`
  (extend `test\Support\DatabaseTestCase`, prefix fixtures with `ZZTEST-`), page output -> golden/smoke tests.
- Run `composer run tests:docker` (full suite on PHP 7.4 + seeded MySQL) and `composer run lint` (PHP 7.3 compatibility) before opening a PR.
- A bug you cannot fix right away becomes a `@group known-bug` test describing the intended behaviour (excluded from the default run).
- See [tests/README.md](tests/README.md) for details.

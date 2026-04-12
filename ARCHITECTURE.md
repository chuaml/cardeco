# Project Architecture

This document describes the architectural patterns and directory structure of the project.

## 1. Request Lifecycle

The application follows a custom Front Controller pattern, with all requests routed through `router.php`.

### **General Flow:**
1.  **Entry Point:** `router.php` initializes the environment (Composer autoloading, database connection).
2.  **Routing:** 
    - The `REQUEST_URI` is mapped to a file in `request_handler/`.
    - Example: `/lazada` -> `request_handler/lazada.php`.
    - Fallback: If no match is found, it attempts to route to `legacy_pages/request_handler/`.
3.  **Handler Execution:** The handler file (e.g., `request_handler/lazada.php`) is executed. It:
    - Performs business logic (often delegating to classes in `src/` or `inc/class/`).
    - Sets a `$_view_html_path` variable (e.g., `view/lazada.html`).
4.  **View Rendering:** 
    - If `$_view_html_path` is set, `router.php` includes `view/_layout.main.html`.
    - `_layout.main.html` wraps the content with common headers, navigation (`inc/html/nav.html`), and then includes the specific view file.

## 2. Directory Structure

| Directory | Purpose |
| :--- | :--- |
| `router.php` | Main entry point and router. |
| `request_handler/` | Controllers that handle page-level requests and map to views. |
| `ajax/` | Endpoints specifically for AJAX requests (legacy/fragmented). |
| `process/` | Backend processing scripts, often for form submissions. |
| `view/` | HTML/PHP templates for the user interface. |
| `src/` | Modern, namespaced PHP classes (PSR-4 recommended). |
| `inc/class/` | Legacy PHP classes (often non-namespaced, loaded via classmap). |
| `db/` | Database connection logic and migration scripts. |
| `js/` / `css/` | Frontend assets. |
| `legacy_pages/` | Older parts of the application being slowly migrated. |

## 3. Key Components & Conventions

### **Database**
- The global variable `$con` (or `$conn` in some files) is initialized in `db/conn_staff.php`.
- Logic should ideally use the `Database_Manager` or namespaced equivalents in `src/`.

### **Class Loading**
- **Modern:** Classes in `src/` should use namespaces (e.g., `namespace OrderProcess;`).
- **Legacy:** Classes in `inc/class/` are being migrated to `src/`.

### **Naming Conventions**
- **Files:** PascalCase for classes (`StockManager.php`), snake_case for some legacy handlers/views.
- **Classes:** PascalCase.
- **Methods/Variables:** camelCase (modern) or snake_case (legacy).

## 4. Documentation Strategy

- **`GEMINI.md`**: Context-specific instructions for AI agents in specific directories.
- **PHPDoc**: All new code must include PHPDoc blocks for type safety and IDE support.
- **API Documentation**: AJAX and form submission endpoints should be documented in a future `API.md`.

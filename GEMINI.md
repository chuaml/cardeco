# Project Mandates

This is a legacy custom software system solution for a specific company. All changes must adhere to the following mandates and technical standards.

## Technical Stack
- **Runtime:** PHP 7.3
- **Database:** MySQL 8
- **Web Server:** Apache 2.4 (LAMP stack in Docker, WAMP stack for legacy/staging/production)
- **Entry Point:** `router.php` (enforced by `.htaccess`). All requests are routed through this file.
- **Routing Logic:** Can be inferred from `router.php`. Most page request pathname follow a 1-to-1 mapping to a PHP file, with either `request_handler/**` or `legacy_pages/request_handler/**` being a primary destination for many requests, including legacy page paths. A request_handler file will use either a HTML or a PHP file from `view/**` with corresponding pathname or filename for displaying UI. 
- **Class Locations:** Most modern class files are stored in `src/**`. Additional classes are located in `inc/class/`.
- **Legacy Compatibility:** Maintain backward compatibility for WAMP stack while developing in Docker-LAMP.

## Development Workflow
- **Environment:** Use the provided Dev Container (`.devcontainer/`) for development.
- **Configuration:** Local overrides in `config.dev/` (e.g., `php-dev-override.ini`, `xdebug.ini`) should remain `assume-unchanged` in Git.
- **Branching Model:** 
  1. `dev` (or feature branches)
  2. `staging` (UAT)
  3. `main` (Live Production)

## Engineering Standards
- **Routing:** Do not create new top-level PHP entry points unless explicitly instructed; use `router.php`.
- **Testing:** Always check `tests/` for existing test cases when modifying core logic. New features should include tests in `tests/`.
- **Git:** Never stage or commit changes to `config.dev/*.ini` files unless they are meant to be global.
- **Security & Privacy:** This software is **PROPRIETARY** and **NOT FREE TO DISTRIBUTE**. Handle all source code and data with appropriate care.

## Tools & Conventions
- **Composer:** Use `composer` for PHP dependencies.
- **NPM:** Use `npm` for frontend dependencies if applicable (see `package.json`).
- **Database Migrations:** Check `db/upgrade.sql` or similar for database schema changes.

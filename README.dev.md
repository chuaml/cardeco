# Dev Setup

locally ignore these custom config files changes:
```bash
git update-index --assume-unchanged config.dev/php-dev-override.ini 
git update-index --assume-unchanged config.dev/xdebug.ini   
git ls-files -v | grep "^h"
```
- these are php and xdebug conf to be addon and custom override locally during dev
- `git update-index --no-assume-unchanged ` to re-add file into tracking changes

Run AND Develop the whole project in a Dev Container
- use VSCODE + `ms-vscode-remote.remote-containers` extension
    * refer in `./setup.dev.bat.md`

# Dev note

configurations of dev enviornment:
- Docker-container + LAMP stack 
    - : for development
- WAMP stack 
    - : for backward-compatibility of staging and production --live server--

these are configurations of dev environment, but do not run these manually in docker
- `./compose.yml`
- `./.devcontainer/devcontainer.json`

php webapp entry point:
- `./router.php` only
    * enforced by `./.htaccess`

changes should be applied to branch accordingly:
1. `dev` or any other branch
2. `staging` for UAT and playground
3. `main` for live production

## PHP versions: one code base, two runtimes

| where | PHP | how |
| :--- | :--- | :--- |
| dev container | 8.3 (and `php7.3` as a second CLI) | `compose.yml` build args `PHP_VERSION=8.3`, `FLOOR_PHP_CLI=7.3` |
| staging / production image | 7.4 | `Dockerfile` default, `setup.staging.bat` / `setup.production.bat` unchanged |
| live production (WAMP) | 7.3 | the floor: `composer.json` `config.platform.php = 7.3.33` |

- after pulling this change: `Dev Containers: Rebuild and Reopen in Container` (the image changed)
- every line must work on PHP 7.3 **and** PHP 8.3; before merging to `staging` / `main` run
    - `composer run check` (inside the container) - PHP 7.3 - 8.3 lint, `php -l`, case audit, PHPStan
    - `composer run lint:php73` (inside the container) - real PHP 7.3 syntax check
    - `composer run tests:matrix` (on the host) - full test suite on PHP 7.3, 7.4 and 8.3
- optional pre-commit hook: `git config core.hooksPath .githooks`
- on the WAMP host after pulling: `composer install --no-dev` then `composer check-platform-reqs --no-dev`
- details: `AGENTS.md` section 2 (rules 1, 4, 5) and section 6



# Project note

legacy custom software system solution for ******* company.

**SOFTWARE IS NOT FREE** AND **NOT FREE TO DISTRIBUTE**

software run in WAMP stack (old and backward compatible) and run in newer Docker-container+LAMP stack.

software spec:
- originally WAMP stack
    - php 7.3
    - mysql 8
    - apache 2.4
- 2026-02-17 onward support container+LAMP stack
    * uses for development purpose


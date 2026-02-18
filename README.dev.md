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


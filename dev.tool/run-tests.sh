#!/bin/sh
# Entrypoint of the `phpunit*` services in compose.test.yml (runs as root inside the PHP 8.3 / 7.4 / 7.3 container).
#
#   docker compose -f compose.test.yml run --rm phpunit [phpunit args]
#
# 1. installs Composer dependencies into the container-only vendor volume (first run, and again whenever
#    composer.lock changed since the last install into that volume)
# 2. regenerates the Composer classmap (required whenever classes are added under src/ or tests/)
# 3. checks that every locked package supports the PHP that is actually running
# 4. starts Apache so tests/Smoke can hit http://localhost (PHP inherits MYSQL_* from this environment)
# 5. runs PHPUnit with any extra arguments; the container exit code == PHPUnit exit code
#
# CARDECO_RUN replaces steps 4-5 (docker compose -f compose.test.yml run --rm -e CARDECO_RUN=<mode> phpunit73):
#   check        `composer run check`: phpcs, php -l, case audit, PHPStan
#   lint-syntax  `php -l` with this container's PHP over first-party code and the production vendor packages
#   audit        `composer run audit:php83`: writes audit-php83-blockers.txt
#
# Anything the run creates inside the bind-mounted project (golden files, result cache, composer.lock)
# is chown'ed back to the host user that owns composer.json, because the container runs as root.
set -eu

cd /var/www/html
export XDEBUG_MODE="${XDEBUG_MODE:-off}"

HOST_OWNER="$(stat -c '%u:%g' composer.json)"
# the bind-mounted project is owned by the host user, not root: stop git/composer from complaining
git config --global --add safe.directory /var/www/html >/dev/null 2>&1 || true
restore_ownership() {
    chown -R "$HOST_OWNER" tests composer.json composer.lock 2>/dev/null || true
    for f in .phpunit.result.cache phpunit.xml .phpcs.cache phpstan-baseline.neon audit-php83-blockers.txt analysis; do
        [ -e "$f" ] && chown -R "$HOST_OWNER" "$f" 2>/dev/null || true
    done
}
trap restore_ownership EXIT

echo ">> PHP $(php -r 'echo PHP_VERSION;')"

# serialised: several `run --rm phpunit` containers may start concurrently and share the vendor volume
LOCK_SUM="$(sha1sum composer.lock | cut -d' ' -f1)"
(
    flock 9
    if [ ! -f vendor/autoload.php ] || [ "$(cat vendor/.composer-lock.sha1 2>/dev/null || true)" != "$LOCK_SUM" ]; then
        echo ">> vendor/ is empty or older than composer.lock in this volume - running composer install"
        composer install --no-interaction --no-progress --ignore-platform-req=ext-zip
        echo "$LOCK_SUM" > vendor/.composer-lock.sha1
    fi
    composer dumpautoload --no-interaction --quiet
) 9> vendor/.install.lock

# composer.json pins config.platform.php to the production floor, so `composer install` cannot notice a package
# that excludes the PHP actually running here. check-platform-reqs ignores that pin and tests the real runtime.
if ! composer check-platform-reqs --no-interaction > /tmp/platform-reqs.txt 2>&1; then
    cat /tmp/platform-reqs.txt
    echo ">> a locked package does not support PHP $(php -r 'echo PHP_VERSION;') or one of its extensions is missing"
    exit 1
fi

case "${CARDECO_RUN:-phpunit}" in
    check)
        composer run check
        exit $?
        ;;
    lint-syntax)
        php dev.tool/lint-syntax.php --with-vendor
        exit $?
        ;;
    audit)
        php dev.tool/audit-php83.php
        exit $?
        ;;
esac

if [ "${SKIP_APACHE:-0}" != "1" ]; then
    # for tests/Smoke; harmless if it fails (those tests then report the HTTP error)
    apachectl start >/dev/null 2>&1 || echo ">> warning: apache did not start; tests/Smoke will fail"
fi

# no `exec`: the EXIT trap (ownership restore) must still run after PHPUnit finishes
set +e
php -d memory_limit=-1 -d error_reporting=-1 -d zend.assertions=1 -d assert.exception=1 \
    vendor/bin/phpunit --colors=always "$@"
exit $?

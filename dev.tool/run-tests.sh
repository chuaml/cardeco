#!/bin/sh
# Entrypoint of the `phpunit` service in compose.test.yml (runs as root inside the PHP 7.4 container).
#
#   docker compose -f compose.test.yml run --rm phpunit [phpunit args]
#
# 1. installs Composer dependencies into the container-only vendor volume (first run only)
# 2. regenerates the Composer classmap (required whenever classes are added under src/ or tests/)
# 3. starts Apache so tests/Smoke can hit http://localhost (PHP inherits MYSQL_* from this environment)
# 4. runs PHPUnit with any extra arguments; the container exit code == PHPUnit exit code
#
# Anything the run creates inside the bind-mounted project (golden files, result cache, composer.lock)
# is chown'ed back to the host user that owns composer.json, because the container runs as root.
set -eu

cd /var/www/html
export XDEBUG_MODE="${XDEBUG_MODE:-off}"

HOST_OWNER="$(stat -c '%u:%g' composer.json)"
restore_ownership() {
    chown -R "$HOST_OWNER" tests composer.json composer.lock 2>/dev/null || true
    for f in .phpunit.result.cache phpunit.xml; do
        [ -e "$f" ] && chown "$HOST_OWNER" "$f" 2>/dev/null || true
    done
}
trap restore_ownership EXIT

if [ ! -f vendor/autoload.php ]; then
    echo ">> vendor/ is empty in this volume - running composer install (first run only)"
    composer install --no-interaction --no-progress --ignore-platform-req=ext-zip
fi
# serialised: several `run --rm phpunit` containers may start concurrently and share the vendor volume
flock vendor/.dumpautoload.lock composer dumpautoload --no-interaction --quiet

if [ "${SKIP_APACHE:-0}" != "1" ]; then
    # for tests/Smoke; harmless if it fails (those tests then report the HTTP error)
    apachectl start >/dev/null 2>&1 || echo ">> warning: apache did not start; tests/Smoke will fail"
fi

# no `exec`: the EXIT trap (ownership restore) must still run after PHPUnit finishes
set +e
php -d memory_limit=-1 -d error_reporting=-1 -d zend.assertions=1 -d assert.exception=1 \
    vendor/bin/phpunit --colors=always "$@"
exit $?

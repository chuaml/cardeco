set XDEBUG_MODE=debug& set XDEBUG_SESSION=1& set XDEBUG_CONFIG=vsc
php -d xdebug.log="CON" -d xdebug.log_level=3 -d xdebug.mode=develop,debug,trace,profile,gcstats  -d xdebug.show_local_vars=1 -d xdebug.start_with_request=yes -d xdebug.start_with_request=yes -d xdebug.idekey=vsc -d xdebug.trigger_value=vsc "%*"
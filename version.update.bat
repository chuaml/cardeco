@echo off
echo updating to the latest version...
call git stash
call git pull
call composer install
call composer dumpautoload

echo ---
echo Update complete.
pause
@echo off

echo current branch
call git branch --show-current
echo ===

echo updating to the latest version...
call git stash
call git fetch --all
call git pull

echo ---
echo Update downloaded.
echo ---

echo ---
echo Installing...
echo ---

call setup.production.bat
echo ---
echo Upgrade complete.
echo ---

pause
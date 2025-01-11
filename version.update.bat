@echo off

echo current branch
call git branch --show-current

echo updating to the latest version...
call git stash
call git fetch --all
call git pull
call composer install
call composer dumpautoload

echo ---
echo Update complete.

echo ---
echo running databasebackup process...
call backup.database.bat

echo ---
echo Update and backup complete!
echo ---

pause
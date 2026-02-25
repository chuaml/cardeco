@echo off
setlocal
echo begin to backup current database...

set webroot=%cd%

:UP_DIRECTORY
cd ..
echo Current directory: %cd%
if not "%cd:~-7%"=="\wamp64" goto UP_DIRECTORY

echo Found wamp64 directory: %cd%

cd bin\mysql
echo %cd%

@REM go to `mysql8***`
for /f "delims=" %%a in ('dir /b ^| findstr mysql8') do (
  if exist "%%a" (
    echo "%%a" 
    cd "%%a"
    goto :break
  ) else (
    echo Directory "%%a" not found.
    goto :break
  )
)
:break

echo %cd%
if errorlevel 1 (
    echo No matching MySQL directory found.
    echo database backup process fail.
)

cd bin
if not exist "%cd%\mysqldump.exe" (
    echo No matching MySQL directory found.
    echo database backup process fail.
    pause
    goto :END
)
echo Found MySQL bin directory
echo %cd%
goto :FOUND_MYSQL


@REM back database
:FOUND_MYSQL
mysqldump --version
set mysql_root=%cd%

echo backuping database
mysqldump -u root --lock-all-tables cardeco > "%webroot%"\db\cardeco.bak.sql
echo backup complate
echo a backup copied in %webroot%\db\cardeco.bak.sql
:END

endlocal
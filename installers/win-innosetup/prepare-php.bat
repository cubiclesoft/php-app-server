@echo off
cls
rem This file prepares PHP App Server for deployment with the Windows installers.
rem The prepare.php file is run by whichever directory contains a valid PHP executable.

if exist php-win-64\php.exe (
  copy /Y php-win-64\php.exe php-win-64\php-temp.exe
  php-win-64\php-temp.exe support\prepare.php
  del php-win-64\php-temp.exe
) else (
  if exist php-win-32\php.exe (
    copy /Y php-win-32\php.exe php-win-32\php-temp.exe
    php-win-32\php-temp.exe support\prepare.php
    del php-win-32\php-temp.exe
  ) else (
    echo PHP binaries not found in parent 'php-win-64' or 'php-win-32' directories.
  )
)

pause

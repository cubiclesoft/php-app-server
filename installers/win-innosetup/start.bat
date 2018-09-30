@echo off
rem This file runs the application in portable app mode.
rem It detects the OS bit level and then runs the appropriate version of PHP with whatever .phpapp file it finds.


reg Query "HKLM\Hardware\Description\System\CentralProcessor\0" | find /i "x86" > NUL && set OS=32BIT || set OS=64BIT

set PHPEXE=php-win.exe
if %OS%==64BIT if exist php-64\php-win.exe set PHPEXE=php-64\php-win.exe
if %OS%==64BIT if not exist php-64\php-win.exe if exist php-32\php-win.exe set PHPEXE=php-32\php-win.exe
if %OS%==32BIT if exist php-32\php-win.exe set PHPEXE=php-32\php-win.exe

for %%f in (*.phpapp) do (
  %PHPEXE% %%f
)

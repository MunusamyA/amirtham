@echo off
setlocal
where composer >nul 2>nul
if errorlevel 1 (
  echo Composer was not found in PATH.
  echo Install Composer from https://getcomposer.org/ and run this file again.
  pause
  exit /b 1
)
composer install --no-dev --optimize-autoloader
if errorlevel 1 (
  echo Dependency installation failed.
  pause
  exit /b 1
)
echo Dependencies installed successfully.
pause

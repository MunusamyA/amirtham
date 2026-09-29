#!/usr/bin/env sh
set -eu
composer install --no-dev --optimize-autoloader
echo "Dependencies installed successfully."

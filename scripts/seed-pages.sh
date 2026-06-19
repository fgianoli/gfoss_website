#!/bin/sh
# Wrapper per eseguire seed-pages.php via wp-cli.
# Usage:
#   docker compose --profile tools run --rm wpcli /scripts/seed-pages.sh
set -e
exec wp --allow-root --path=/var/www/html eval-file /scripts/seed-pages.php

#!/bin/sh
# Wrapper per eseguire seed-pages.php via wp-cli.
# L'entrypoint del servizio wpcli è già "wp", quindi usa direttamente:
#   docker compose --profile tools run --rm wpcli eval-file /scripts/seed-pages.php
# (Questo .sh va invocato solo forzando l'entrypoint: --entrypoint sh)
set -e
exec wp --allow-root --path=/var/www/html eval-file /scripts/seed-pages.php

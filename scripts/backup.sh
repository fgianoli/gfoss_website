#!/bin/sh
# Backup di GFOSS.it: database (dump) + cartella uploads, con rotazione.
#
# Uso (dalla cartella del progetto, es. /opt/gfoss-wp):
#   ./scripts/backup.sh [cartella_destinazione]
# Default destinazione: /opt/backups/gfoss
#
# Cron consigliato (ogni notte alle 02:30):
#   30 2 * * * cd /opt/gfoss-wp && ./scripts/backup.sh >> /var/log/gfoss-backup.log 2>&1
#
# Ripristino DB:
#   gunzip < db-AAAA-MM-GG-HHMM.sql.gz | docker compose exec -T db sh -c \
#     'mariadb -u root -p"$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE"'

set -eu

DEST="${1:-/opt/backups/gfoss}"
KEEP_DAYS="${KEEP_DAYS:-30}"
STAMP="$(date +%F-%H%M)"

mkdir -p "$DEST"

echo "[$(date '+%F %T')] Backup avviato → $DEST"

# 1. Database (compresso)
docker compose exec -T db sh -c 'exec mariadb-dump --single-transaction --quick -u root -p"$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE"' \
  | gzip > "$DEST/db-$STAMP.sql.gz"
echo "  ✓ DB: db-$STAMP.sql.gz"

# 2. Uploads (media): la cartella vive accanto a questo script, in wp-content/uploads
if [ -d wp-content/uploads ]; then
  tar czf "$DEST/uploads-$STAMP.tar.gz" wp-content/uploads
  echo "  ✓ uploads: uploads-$STAMP.tar.gz"
fi

# 3. Rotazione: elimina i backup più vecchi di KEEP_DAYS giorni
find "$DEST" -name 'db-*.sql.gz'       -mtime +"$KEEP_DAYS" -delete
find "$DEST" -name 'uploads-*.tar.gz'  -mtime +"$KEEP_DAYS" -delete

echo "[$(date '+%F %T')] Backup completato (mantengo ultimi $KEEP_DAYS giorni)."

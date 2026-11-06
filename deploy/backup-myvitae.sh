#!/usr/bin/env bash
#
# Nightly backup for the production MyVitae deployment.
# Database dump + private storage (CV uploads, exports). Retention: 7 days.
#
# Install:
#   sudo cp deploy/backup-myvitae.sh /usr/local/bin/backup-myvitae
#   sudo chmod 755 /usr/local/bin/backup-myvitae
#   sudo crontab -e   ->   15 3 * * * /usr/local/bin/backup-myvitae
#
set -euo pipefail

APP_DIR="/var/www/myvitae"
BACKUP_DIR="/var/backups/myvitae"
RETENTION_DAYS=7
STAMP="$(date +%Y%m%d-%H%M%S)"

# Credentials come from the app's .env so this script never stores a password.
DB_NAME="$(grep -E '^DB_DATABASE=' "$APP_DIR/.env" | cut -d= -f2-)"
DB_USER="$(grep -E '^DB_USERNAME=' "$APP_DIR/.env" | cut -d= -f2-)"
DB_PASS="$(grep -E '^DB_PASSWORD=' "$APP_DIR/.env" | cut -d= -f2-)"

install -d -m 700 "$BACKUP_DIR"

# --single-transaction keeps InnoDB consistent without locking the app.
MYSQL_PWD="$DB_PASS" mysqldump \
    --single-transaction --quick --no-tablespaces \
    -h 127.0.0.1 -u "$DB_USER" "$DB_NAME" \
    | gzip > "$BACKUP_DIR/db-$STAMP.sql.gz"

# Private disk: uploaded CVs live here. Exports are reproducible, but cheap to keep.
tar -czf "$BACKUP_DIR/storage-$STAMP.tar.gz" \
    -C "$APP_DIR/storage/app" private 2>/dev/null || true

# Drop anything past the retention window.
find "$BACKUP_DIR" -type f -name '*.gz' -mtime +"$RETENTION_DAYS" -delete

echo "backup complete: $BACKUP_DIR (db + storage, $STAMP)"

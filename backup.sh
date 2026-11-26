#!/usr/bin/env bash
#
# Alom's English Center LMS - database, .env, public media and private uploads backup (no code changes).
# Use this before any manual change, or from a daily cron job.
#
#   ./backup.sh
#   0 3 * * * cd /path/to/lms && ./backup.sh >> storage/logs/backup.log 2>&1
#
# Backups land in storage/backups/ and the 30 newest are kept.
#
set -uo pipefail
umask 077
APP_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$APP_DIR" || exit 1

PHP_BIN="${PHP_BIN:-php}"
STAMP="$(date +%Y%m%d-%H%M%S)"
BACKUP_DIR="storage/backups"
mkdir -p "$BACKUP_DIR"
chmod 700 "$BACKUP_DIR"

[ -f .env ] || { echo "no .env here - run from the Laravel project root"; exit 1; }
cp -p .env "$BACKUP_DIR/env-$STAMP.bak" || { echo ".env backup failed"; exit 1; }
chmod 600 "$BACKUP_DIR/env-$STAMP.bak"
[ -s "$BACKUP_DIR/env-$STAMP.bak" ] || { echo ".env backup is empty"; exit 1; }
echo ".env    -> $BACKUP_DIR/env-$STAMP.bak"

ENV_VAL() { grep -E "^$1=" .env | tail -1 | cut -d= -f2- | tr -d '"' | tr -d "'" | tr -d '\r'; }
DB_CONNECTION="$(ENV_VAL DB_CONNECTION)"; DB_CONNECTION="${DB_CONNECTION:-sqlite}"

case "$DB_CONNECTION" in
  sqlite)
    DB_FILE="${DB_DATABASE:-$(ENV_VAL DB_DATABASE)}"; DB_FILE="${DB_FILE:-database/database.sqlite}"
    [ -f "$DB_FILE" ] || { echo "missing sqlite database at $DB_FILE"; exit 1; }
    cp -p "$DB_FILE" "$BACKUP_DIR/sqlite-$STAMP.sqlite" && [ -s "$BACKUP_DIR/sqlite-$STAMP.sqlite" ] || { echo "sqlite backup failed"; exit 1; }
    chmod 600 "$BACKUP_DIR/sqlite-$STAMP.sqlite"
    echo "database -> $BACKUP_DIR/sqlite-$STAMP.sqlite"
    ;;
  mysql)
    command -v mysqldump >/dev/null 2>&1 || { echo "mysqldump missing - export from phpMyAdmin"; exit 1; }
    MYSQL_PWD="$(ENV_VAL DB_PASSWORD)" mysqldump --single-transaction --quick --no-tablespaces \
      -h"$(ENV_VAL DB_HOST)" -P"$(ENV_VAL DB_PORT)" -u"$(ENV_VAL DB_USERNAME)" \
      "${DB_DATABASE:-$(ENV_VAL DB_DATABASE)}" > "$BACKUP_DIR/mysql-$STAMP.sql" 2>/dev/null
    [ -s "$BACKUP_DIR/mysql-$STAMP.sql" ] || { echo "dump failed (empty file)"; rm -f "$BACKUP_DIR/mysql-$STAMP.sql"; exit 1; }
    echo "database -> $BACKUP_DIR/mysql-$STAMP.sql"
    ;;
  pgsql)
    command -v pg_dump >/dev/null 2>&1 || { echo "pg_dump missing"; exit 1; }
    PGPASSWORD="$(ENV_VAL DB_PASSWORD)" pg_dump -h "$(ENV_VAL DB_HOST)" -p "$(ENV_VAL DB_PORT)" \
      -U "$(ENV_VAL DB_USERNAME)" -f "$BACKUP_DIR/pgsql-$STAMP.sql" "$(ENV_VAL DB_DATABASE)" || exit 1
    [ -s "$BACKUP_DIR/pgsql-$STAMP.sql" ] || { echo "PostgreSQL backup is empty"; exit 1; }
    echo "database -> $BACKUP_DIR/pgsql-$STAMP.sql"
    ;;
  *) echo "unsupported DB_CONNECTION: $DB_CONNECTION"; exit 1 ;;
esac

if [ -d storage/app/public ] && find storage/app/public -type f -print -quit | grep -q .; then
  tar -czf "$BACKUP_DIR/uploads-$STAMP.tar.gz" -C storage/app public || { echo "uploaded files backup failed"; exit 1; }
  tar -tzf "$BACKUP_DIR/uploads-$STAMP.tar.gz" >/dev/null 2>&1 || { echo "uploaded files backup verification failed"; exit 1; }
  echo "uploads -> $BACKUP_DIR/uploads-$STAMP.tar.gz"
fi

# Private submissions and learning files must be included without recursively
# copying the Admin database-backup directory into every archive.
if [ -d storage/app/private ] && find storage/app/private -mindepth 1 -maxdepth 1 ! -name system-backups -print -quit | grep -q .; then
  tar -czf "$BACKUP_DIR/private-$STAMP.tar.gz" --exclude='private/system-backups' -C storage/app private || { echo "private uploads backup failed"; exit 1; }
  tar -tzf "$BACKUP_DIR/private-$STAMP.tar.gz" >/dev/null 2>&1 || { echo "private uploads backup verification failed"; exit 1; }
  chmod 600 "$BACKUP_DIR/private-$STAMP.tar.gz"
  echo "private uploads -> $BACKUP_DIR/private-$STAMP.tar.gz"
fi

# Retain complete timestamped backup sets, not an arbitrary number of files.
mapfile -t SNAPSHOTS < <(
  for artifact in "$BACKUP_DIR"/*; do
    [ -f "$artifact" ] || continue
    basename "$artifact" | sed -nE 's/^(env|sqlite|mysql|pgsql|uploads|private)-([0-9]{8}-[0-9]{6})(\\..*)?$/\\2/p'
  done | sort -ru
)
if [ "${#SNAPSHOTS[@]}" -gt 30 ]; then
  for old_stamp in "${SNAPSHOTS[@]:30}"; do
    rm -f "$BACKUP_DIR/"*-"$old_stamp".*
  done
fi
echo "done ($(ls -1 "$BACKUP_DIR" | wc -l | tr -d ' ') files in $BACKUP_DIR)"

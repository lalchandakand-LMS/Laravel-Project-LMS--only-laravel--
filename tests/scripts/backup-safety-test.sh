#!/usr/bin/env bash
set -euo pipefail

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
TMP_DIR="$(mktemp -d)"
trap 'rm -rf "$TMP_DIR"' EXIT

PROJECT="$TMP_DIR/project"
BIN="$TMP_DIR/bin"
mkdir -p "$PROJECT/storage/app/private/documents" \
         "$PROJECT/storage/app/private/system-backups" \
         "$PROJECT/storage/app/public/courses" "$PROJECT/storage/backups" "$BIN"

cp "$REPO_ROOT/backup.sh" "$PROJECT/backup.sh"
chmod +x "$PROJECT/backup.sh"
printf 'private student document\n' > "$PROJECT/storage/app/private/documents/student.pdf"
printf 'old database backup\n' > "$PROJECT/storage/app/private/system-backups/old.sqlite"
printf 'public course image\n' > "$PROJECT/storage/app/public/courses/course.jpg"

# Seed old backup sets so retention can prove it keeps whole snapshots.
for index in $(seq 1 32); do
  stamp="$(printf '20260101-%06d' "$index")"
  : > "$PROJECT/storage/backups/env-$stamp.bak"
  : > "$PROJECT/storage/backups/mysql-$stamp.sql"
  : > "$PROJECT/storage/backups/uploads-$stamp.tar.gz"
  : > "$PROJECT/storage/backups/private-$stamp.tar.gz"
done

cat > "$PROJECT/.env" <<'ENV'
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_USERNAME=lms_test
DB_PASSWORD=backup-test-secret
DB_DATABASE=lms_test
ENV

cat > "$BIN/mysqldump" <<'STUB'
#!/usr/bin/env bash
printf '%s' "$*" > "$CAPTURE_ARGS"
printf '%s' "${MYSQL_PWD:-}" > "$CAPTURE_PASSWORD"
printf 'test database dump\n'
STUB
chmod +x "$BIN/mysqldump"

(
  cd "$PROJECT"
  PATH="$BIN:$PATH" \
  CAPTURE_ARGS="$TMP_DIR/args.txt" \
  CAPTURE_PASSWORD="$TMP_DIR/password.txt" \
  ./backup.sh >/dev/null
)

ARGS="$(cat "$TMP_DIR/args.txt")"
PASSWORD="$(cat "$TMP_DIR/password.txt")"
if [[ "$ARGS" == *"backup-test-secret"* ]]; then
  echo "Database password leaked into mysqldump process arguments." >&2
  exit 1
fi
[[ "$PASSWORD" == "backup-test-secret" ]] || {
  echo "MYSQL_PWD was not passed to mysqldump." >&2
  exit 1
}

PRIVATE_ARCHIVE="$(find "$PROJECT/storage/backups" -maxdepth 1 -name 'private-*.tar.gz' -print -quit)"
[[ -n "$PRIVATE_ARCHIVE" ]] || {
  echo "Private upload archive was not created." >&2
  exit 1
}
tar -tzf "$PRIVATE_ARCHIVE" | grep -Fq 'private/documents/student.pdf'
if tar -tzf "$PRIVATE_ARCHIVE" | grep -Fq 'private/system-backups/old.sqlite'; then
  echo "Admin database backups must not be recursively included in private upload archives." >&2
  exit 1
fi

[[ "$(stat -c '%a' "$PROJECT/storage/backups")" == "700" ]]
[[ "$(stat -c '%a' "$PRIVATE_ARCHIVE")" == "600" ]]
[[ "$(stat -c '%a' "$PROJECT/storage/backups"/env-*.bak)" == "600" ]]

mapfile -t RETAINED_STAMPS < <(
  for artifact in "$PROJECT/storage/backups"/*; do
    basename "$artifact" | sed -nE 's/^(env|sqlite|mysql|pgsql|uploads|private)-([0-9]{8}-[0-9]{6})(\\..*)?/\\2/p'
  done | sort -u
)
[[ "${#RETAINED_STAMPS[@]}" -eq 30 ]] || {
  echo "Backup retention should keep 30 complete snapshots." >&2
  exit 1
}
for stamp in "${RETAINED_STAMPS[@]}"; do
  count="$(find "$PROJECT/storage/backups" -maxdepth 1 -type f -name "*-$stamp.*" | wc -l | tr -d ' ')"
  [[ "$count" -eq 4 ]] || {
    echo "Backup retention left an incomplete snapshot: $stamp" >&2
    exit 1
  }
done

for script_file in "$REPO_ROOT/backup.sh" "$REPO_ROOT/update.sh"; do
  if grep -Fq -- '-p"$(ENV_VAL DB_PASSWORD)"' "$script_file"; then
    echo "A database password must not be passed as a command-line argument: $script_file" >&2
    exit 1
  fi
  grep -Fq 'MYSQL_PWD="$(ENV_VAL DB_PASSWORD)" mysqldump' "$script_file"
done

echo "Backup safety checks passed."

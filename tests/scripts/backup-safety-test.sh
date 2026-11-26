#!/usr/bin/env bash
set -euo pipefail

REPO_ROOT="$(cd "$(dirname "\${BASH_SOURCE[0]}")/../.." && pwd)"
TMP_DIR="$(mktemp -d)"
trap 'rm -rf "$TMP_DIR"' EXIT

PROJECT="$TMP_DIR/project"
BIN="$TMP_DIR/bin"
mkdir -p "$PROJECT/storage/app/private/documents" \
         "$PROJECT/storage/app/private/system-backups" \
         "$PROJECT/storage/app/public/courses" "$PROJECT/storage/backups" "$BIN"

cp "$REPO_ROOT/backup.sh" "$PROJECT/backup.sh"
printf 'private student document\n' > "$PROJECT/storage/app/private/documents/student.pdf"
printf 'old database backup\n' > "$PROJECT/storage/app/private/system-backups/old.sqlite"
printf 'public course image\n' > "$PROJECT/storage/app/public/courses/course.jpg"

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
printf '%s' "\${MYSQL_PWD:-}" > "$CAPTURE_PASSWORD"
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

for script_file in "$REPO_ROOT/backup.sh" "$REPO_ROOT/update.sh"; do
  if grep -Fq -- '-p"$(ENV_VAL DB_PASSWORD)"' "$script_file"; then
    echo "A database password must not be passed as a command-line argument: $script_file" >&2
    exit 1
  fi
  grep -Fq 'MYSQL_PWD="$(ENV_VAL DB_PASSWORD)" mysqldump' "$script_file"
done

echo "Backup safety checks passed."

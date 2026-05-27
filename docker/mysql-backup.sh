#!/usr/bin/env bash
set -euo pipefail

MYSQL_HOST="${MYSQL_HOST:-mysql}"
MYSQL_PORT="${MYSQL_PORT:-3306}"
MYSQL_DATABASE="${MYSQL_DATABASE:-beautycrmapi}"
MYSQL_USER="${MYSQL_USER:-beautycrm}"
MYSQL_PASSWORD="${MYSQL_PASSWORD:-beautycrm}"

BACKUP_DIR="${BACKUP_DIR:-/backups/mysql}"
INTERVAL_MIN="${MYSQL_BACKUP_INTERVAL_MIN:-${INTERVAL_MIN:-360}}"
RETENTION_DAYS="${MYSQL_BACKUP_RETENTION_DAYS:-${RETENTION_DAYS:-14}}"

BACKUP_S3_ENABLED="${BACKUP_S3_ENABLED:-false}"
BACKUP_S3_BUCKET="${BACKUP_S3_BUCKET:-}"
BACKUP_S3_PREFIX="${BACKUP_S3_PREFIX:-beautycrm/backups}"
BACKUP_S3_ENDPOINT="${BACKUP_S3_ENDPOINT:-${AWS_ENDPOINT:-}}"
BACKUP_S3_REGION="${BACKUP_S3_REGION:-${AWS_DEFAULT_REGION:-us-east-1}}"
BACKUP_S3_SSE="${BACKUP_S3_SSE:-}"
BACKUP_S3_RETENTION_DAYS="${BACKUP_S3_RETENTION_DAYS:-14}"
MYSQL_BACKUP_DELETE_LOCAL_AFTER_S3_UPLOAD="${MYSQL_BACKUP_DELETE_LOCAL_AFTER_S3_UPLOAD:-true}"

mkdir -p "$BACKUP_DIR"

aws_s3() {
  if [ -n "$BACKUP_S3_ENDPOINT" ]; then
    aws --endpoint-url "$BACKUP_S3_ENDPOINT" --region "$BACKUP_S3_REGION" "$@"
    return
  fi
  aws --region "$BACKUP_S3_REGION" "$@"
}

upload_to_s3() {
  local file="$1"
  local key="$2"

  if [ "$BACKUP_S3_ENABLED" != "true" ]; then
    return 2
  fi

  if [ -z "$BACKUP_S3_BUCKET" ]; then
    echo "[mysql-backup] BACKUP_S3_ENABLED=true, but BACKUP_S3_BUCKET is empty. Skip S3 upload."
    return 2
  fi

  echo "[mysql-backup] uploading to s3://$BACKUP_S3_BUCKET/$key"
  local sse_args=()
  if [ -n "$BACKUP_S3_SSE" ] && [ "$BACKUP_S3_SSE" != "none" ]; then
    sse_args=(--sse "$BACKUP_S3_SSE")
  fi

  if ! aws_s3 s3 cp "$file" "s3://$BACKUP_S3_BUCKET/$key" "${sse_args[@]}"; then
    echo "[mysql-backup] S3 upload failed for $file. Backup kept locally."
    return 1
  fi

  return 0
}

prune_s3() {
  local prefix="$1"

  if [ "$BACKUP_S3_ENABLED" != "true" ] || [ -z "$BACKUP_S3_BUCKET" ]; then
    echo "[mysql-backup] S3 prune skipped. enabled=$BACKUP_S3_ENABLED bucket_set=$([ -n "$BACKUP_S3_BUCKET" ] && echo yes || echo no)"
    return
  fi

  local now_epoch cutoff_epoch
  now_epoch="$(date -u +%s)"
  cutoff_epoch="$((now_epoch - BACKUP_S3_RETENTION_DAYS * 86400))"
  echo "[mysql-backup] checking S3 retention. bucket=$BACKUP_S3_BUCKET prefix=$prefix/ cutoff_epoch=$cutoff_epoch retention_days=$BACKUP_S3_RETENTION_DAYS"

  local list_output
  if ! list_output="$(aws_s3 s3api list-objects-v2 \
    --bucket "$BACKUP_S3_BUCKET" \
    --prefix "$prefix/" \
    --query 'Contents[].[Key,LastModified]' \
    --output text 2>&1)"; then
    echo "[mysql-backup] S3 prune list failed: $list_output"
    return
  fi

  if [ -z "$list_output" ] || [ "$list_output" = "None" ]; then
    echo "[mysql-backup] no S3 objects found under prefix"
    return
  fi

  local old_count=0
  while IFS=$'\t' read -r key last_modified_raw _rest; do
    [ -z "${key:-}" ] && continue
    [ -z "${last_modified_raw:-}" ] && continue

    local last_modified_epoch
    if ! last_modified_epoch="$(date -u -d "$last_modified_raw" +%s 2>/dev/null)"; then
      echo "[mysql-backup] failed to parse LastModified for key=$key value=$last_modified_raw"
      continue
    fi

    if [ "$last_modified_epoch" -gt "$cutoff_epoch" ]; then
      continue
    fi

    old_count=$((old_count + 1))
    echo "[mysql-backup] deleting old S3 object: $key"
    local delete_output
    if ! delete_output="$(aws_s3 s3api delete-object --bucket "$BACKUP_S3_BUCKET" --key "$key" 2>&1)"; then
      echo "[mysql-backup] failed to delete S3 object: $key"
      echo "[mysql-backup] delete error: $delete_output"
      continue
    fi
    echo "[mysql-backup] deleted S3 object: $key"
  done <<< "$list_output"

  if [ "$old_count" -eq 0 ]; then
    echo "[mysql-backup] no S3 objects matched retention cutoff"
  else
    echo "[mysql-backup] S3 prune finished. matched_old_objects=$old_count"
  fi
}

echo "[mysql-backup] waiting for mysql..."
for i in $(seq 1 120); do
  if mysqladmin ping -h "$MYSQL_HOST" -P "$MYSQL_PORT" -u"$MYSQL_USER" -p"$MYSQL_PASSWORD" --silent >/dev/null 2>&1; then
    break
  fi
  sleep 1
done

echo "[mysql-backup] started. interval=${INTERVAL_MIN}min retention=${RETENTION_DAYS}d dir=${BACKUP_DIR}"

while true; do
  TS="$(date -u +%Y%m%d_%H%M%S)"
  FILE="$BACKUP_DIR/${MYSQL_DATABASE}_${TS}.sql.gz"
  S3_KEY="${BACKUP_S3_PREFIX%/}/mysql/${MYSQL_DATABASE}_${TS}.sql.gz"

  echo "[mysql-backup] dumping to $FILE"
  mysqldump -h "$MYSQL_HOST" -P "$MYSQL_PORT" -u"$MYSQL_USER" -p"$MYSQL_PASSWORD" \
    --single-transaction --quick --routines --triggers --events --no-tablespaces \
    "$MYSQL_DATABASE" | gzip -9 > "$FILE"

  if upload_to_s3 "$FILE" "$S3_KEY"; then
    if [ "$MYSQL_BACKUP_DELETE_LOCAL_AFTER_S3_UPLOAD" = "true" ]; then
      echo "[mysql-backup] S3 upload successful. Deleting local backup: $FILE"
      rm -f "$FILE"
    fi
  fi

  # Local retention
  find "$BACKUP_DIR" -type f -name "*.sql.gz" -mtime +"$RETENTION_DAYS" -delete 2>/dev/null || true

  # S3 retention
  prune_s3 "${BACKUP_S3_PREFIX%/}/mysql"

  sleep "$((INTERVAL_MIN * 60))"
done

#!/usr/bin/env bash
# Backup do PostgreSQL do Finança+ (formato custom do pg_dump, restaurável com scripts/restore.sh).
#
# Uso:
#   scripts/backup.sh                  # faz um backup agora
#   scripts/backup.sh --se-faltar-hoje # só faz se ainda não houver backup de hoje (usado pelo cron)
#
# Variáveis: KEEP (quantos backups manter, padrão 14), BACKUP_DIR (padrão ./backups).
set -euo pipefail

cd "$(dirname "$0")/.."
BACKUP_DIR="${BACKUP_DIR:-$PWD/backups}"
KEEP="${KEEP:-14}"
mkdir -p "$BACKUP_DIR"

if [[ "${1:-}" == "--se-faltar-hoje" ]] && compgen -G "$BACKUP_DIR/financaplus_$(date +%F)_*.dump" > /dev/null; then
  exit 0
fi

if ! docker compose ps --status running --services 2>/dev/null | grep -qx db; then
  echo "$(date '+%F %T') ERRO: serviço db não está rodando; backup não feito." >&2
  exit 1
fi

arquivo="$BACKUP_DIR/financaplus_$(date +%F_%H%M).dump"
tmp="$arquivo.parcial"
trap 'rm -f "$tmp"' EXIT

docker compose exec -T db sh -c 'pg_dump -U "$POSTGRES_USER" -d "$POSTGRES_DB" -Fc' > "$tmp"

# Confere se o arquivo é um dump legível antes de considerá-lo válido.
docker compose exec -T db pg_restore --list < "$tmp" > /dev/null
mv "$tmp" "$arquivo"
chmod 600 "$arquivo"
trap - EXIT

# Retenção: mantém só os KEEP mais recentes.
ls -1t "$BACKUP_DIR"/financaplus_*.dump 2>/dev/null | tail -n +"$((KEEP + 1))" | xargs -r rm -f --

echo "$(date '+%F %T') OK: $arquivo ($(du -h "$arquivo" | cut -f1))"

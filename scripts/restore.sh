#!/usr/bin/env bash
# Restaura um backup feito por scripts/backup.sh. SUBSTITUI os dados atuais do banco.
#
# Uso: scripts/restore.sh backups/financaplus_AAAA-MM-DD_HHMM.dump
set -euo pipefail

cd "$(dirname "$0")/.."
arquivo="${1:?Informe o arquivo .dump a restaurar}"
[[ -f "$arquivo" ]] || { echo "Arquivo não encontrado: $arquivo" >&2; exit 1; }

read -r -p "Isso substitui os dados atuais pelo conteúdo de $arquivo. Digite SIM para continuar: " ok
[[ "$ok" == "SIM" ]] || { echo "Cancelado."; exit 1; }

# Backup de segurança do estado atual antes de sobrescrever.
scripts/backup.sh

docker compose stop worker scheduler
docker compose exec -T db sh -c 'pg_restore -U "$POSTGRES_USER" -d "$POSTGRES_DB" --clean --if-exists --no-owner' < "$arquivo"
docker compose start worker scheduler
echo "Restaurado: $arquivo"

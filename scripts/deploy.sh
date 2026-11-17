#!/usr/bin/env bash
# Publica a versão mais recente do main neste servidor. Roda NO SERVIDOR, na pasta do projeto.
# É o mesmo script usado à mão e pela pipeline do GitHub Actions (.github/workflows/deploy.yml).
#
# Passos: backup do banco → git pull → build → recria app/worker/scheduler → migrations → teste de saúde.
# Se o build falhar, os contêineres antigos continuam no ar.
set -euo pipefail

cd "$(dirname "$0")/.."

# Um deploy por vez.
exec 9> /tmp/glamfinancas-deploy.lock
flock -n 9 || { echo "Já existe um deploy em andamento." >&2; exit 1; }

antes="$(git rev-parse --short HEAD)"
git fetch --quiet origin main
git merge --ff-only --quiet origin/main
depois="$(git rev-parse --short HEAD)"
echo "==> Código: $antes → $depois"

echo "==> Backup do banco"
docker compose up -d db
scripts/backup.sh

echo "==> Build"
docker compose build --quiet

echo "==> Recriando contêineres"
docker compose up -d --force-recreate app worker scheduler

echo "==> Migrations"
for _ in $(seq 1 30); do
  docker compose exec -T app php artisan --version > /dev/null 2>&1 && break
  sleep 2
done
docker compose exec -T app php artisan migrate --force

echo "==> Teste de saúde"
for _ in $(seq 1 15); do
  codigo="$(curl -s -o /dev/null -w '%{http_code}' http://127.0.0.1:8088/up || true)"
  [[ "$codigo" == "200" ]] && break
  sleep 2
done
[[ "$codigo" == "200" ]] || { echo "ERRO: a aplicação respondeu $codigo em /up depois do deploy." >&2; exit 1; }

docker image prune -f > /dev/null
echo "==> Publicado: $depois ($(git log -1 --format=%s))"

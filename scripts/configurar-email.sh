#!/usr/bin/env bash
# Configura o envio de e-mails (SMTP) no .env e manda um e-mail de teste.
# A senha/chave é digitada aqui e vai direto para o .env (que não vai para o git).
#
#   resend -> domínio próprio (ex.: nao-responda@vyrasolutions.com.br) verificado no Resend.
#             SMTP smtp.resend.com:587, usuário "resend", senha = API key (começa com re_)
#   gmail  -> conta do Gmail com "senha de app" (https://myaccount.google.com/apppasswords)
#   outro  -> qualquer servidor SMTP (pergunta host, usuário e senha)
#
# Uso: scripts/configurar-email.sh [resend|gmail|outro]
set -euo pipefail
cd "$(dirname "$0")/.."
[[ -f .env ]] || { echo "Arquivo .env não encontrado." >&2; exit 1; }

PROVEDOR="${1:-}"
if [[ -z "$PROVEDOR" ]]; then
  read -r -p "Provedor (resend/gmail/outro) [resend]: " PROVEDOR
  PROVEDOR="${PROVEDOR:-resend}"
fi

case "$PROVEDOR" in
  resend)
    HOST=smtp.resend.com; PORTA=587; USUARIO=resend
    read -r -p "Remetente (ex.: nao-responda@vyrasolutions.com.br): " REMETENTE
    read -r -s -p "API key do Resend (começa com re_): " SENHA; echo
    [[ "$SENHA" == re_* ]] || { echo "A API key do Resend começa com re_. Confira e rode de novo." >&2; exit 1; }
    ;;
  gmail)
    HOST=smtp.gmail.com; PORTA=587
    read -r -p "Seu endereço do Gmail: " USUARIO; REMETENTE="$USUARIO"
    read -r -s -p "Senha de app (16 letras; os espaços são ignorados): " SENHA; echo
    SENHA="${SENHA// /}"
    [[ ${#SENHA} -eq 16 ]] || { echo "A senha de app do Google tem 16 letras; recebi ${#SENHA}. Confira e rode de novo." >&2; exit 1; }
    ;;
  outro)
    read -r -p "Servidor SMTP: " HOST
    read -r -p "Porta [587]: " PORTA; PORTA="${PORTA:-587}"
    read -r -p "Usuário: " USUARIO
    read -r -p "Remetente [$USUARIO]: " REMETENTE; REMETENTE="${REMETENTE:-$USUARIO}"
    read -r -s -p "Senha: " SENHA; echo
    ;;
  *) echo "Provedor desconhecido: $PROVEDOR (use resend, gmail ou outro)" >&2; exit 1 ;;
esac

[[ "$REMETENTE" == *@*.* ]] || { echo "Remetente inválido." >&2; exit 1; }
[[ -n "$SENHA" ]] || { echo "Senha vazia." >&2; exit 1; }
# A senha vai entre aspas simples no .env (o Docker Compose expandiria "$" dentro de aspas duplas),
# e aspas simples não podem conter o caractere '.
[[ "$SENHA" != *"'"* ]] || { echo "A senha não pode conter aspas simples ('). Troque a senha e rode de novo." >&2; exit 1; }
read -r -p "Nome do remetente [GlamFinanças]: " NOME
NOME="${NOME:-GlamFinanças}"

cp .env ".env.bak-$(date +%F_%H%M%S)"
chmod 600 .env.bak-* 2>/dev/null || true

USUARIO="$USUARIO" REMETENTE="$REMETENTE" SENHA="$SENHA" HOST="$HOST" PORTA="$PORTA" NOME="$NOME" python3 - <<'PY'
import os, re
def literal(v):  # aspas simples: nem o Laravel nem o Docker Compose interpretam $ ou \ aqui
    return "'" + v + "'"
valores = {
    'MAIL_MAILER': 'smtp',
    'MAIL_HOST': os.environ['HOST'],
    'MAIL_PORT': os.environ['PORTA'],
    'MAIL_SCHEME': 'smtps' if os.environ['PORTA'] == '465' else 'null',
    'MAIL_USERNAME': os.environ['USUARIO'],
    'MAIL_PASSWORD': literal(os.environ['SENHA']),
    'MAIL_FROM_ADDRESS': os.environ['REMETENTE'],
    'MAIL_FROM_NAME': literal(os.environ['NOME'].replace("'", '')),
}
linhas = open('.env').read().splitlines()
vistos = set()
for i, l in enumerate(linhas):
    m = re.match(r'^([A-Z_]+)=', l)
    if m and m.group(1) in valores:
        linhas[i] = f'{m.group(1)}={valores[m.group(1)]}'
        vistos.add(m.group(1))
linhas += [f'{k}={v}' for k, v in valores.items() if k not in vistos]
open('.env', 'w').write('\n'.join(linhas) + '\n')
PY
# (não use chmod 600 no .env: ele é montado no container e lido pelo usuário www-data)
unset SENHA

echo "Reiniciando app e worker para ler o .env novo..."
docker compose up -d --force-recreate app worker >/dev/null
sleep 5
read -r -p "Mandar o e-mail de teste para qual endereço? " PARA
docker compose exec -T -u www-data app php artisan finance:testar-email "$PARA"

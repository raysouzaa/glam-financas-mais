# Publicação em produção

O servidor roda os mesmos contêineres do desenvolvimento, com os ajustes de [compose.prod.yaml](compose.prod.yaml): a aplicação escuta só em `127.0.0.1:8088`, e um proxy reverso no próprio servidor (Caddy) cuida do domínio e do HTTPS. O banco de produção começa a partir de um dump do banco local.

Nos comandos abaixo, troque `usuario@servidor` pelo acesso SSH e `<seu-dominio>` pelo domínio. A produção atual fica em `/var/www/html/financas-plus`, no domínio `glamfinancasmais.vyrasolutions.com.br`.

O nome da pasta define o nome do projeto no Compose e, com ele, o do volume do banco (`financas-plus_pgdata`). Renomear a pasta faz o Compose criar um banco vazio.

## Antes de começar

- Servidor Linux com Docker Engine e Docker Compose 2.24 ou mais novo (`docker compose version`).
- Um registro DNS do tipo A apontando o domínio para o IP do servidor.
- Portas 80 e 443 liberadas no firewall. A 8088 não precisa ser aberta.

## 1. Código no servidor

O repositório é privado, então o servidor precisa de uma chave de leitura (GitHub → Settings → Deploy keys do repositório).

```bash
ssh usuario@servidor
sudo mkdir -p /var/www/html/financas-plus && sudo chown "$USER" /var/www/html/financas-plus
git clone git@github.com:raysouzaa/glam-financas-mais.git /var/www/html/financas-plus
cd /var/www/html/financas-plus
```

## 2. Arquivo .env

```bash
cp .env.producao.example .env
chmod 600 .env
nano .env
```

Preencha `APP_URL`, `DB_PASSWORD` (`openssl rand -hex 24` gera uma) e `MAIL_PASSWORD` (a mesma API key do Resend que está no `.env` local). Deixe `APP_KEY` vazio por enquanto.

## 3. Pastas de dados

A pasta `storage` fica fora da imagem e precisa pertencer ao usuário do PHP dentro do contêiner (uid 82).

```bash
mkdir -p storage/app/private storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs backups
sudo chown -R 82:82 storage
```

## 4. Imagem e chave da aplicação

```bash
docker compose build
docker compose up -d db
docker compose run --rm app php artisan key:generate --force
```

## 5. Enviar e restaurar o dump

Na máquina local, gere um dump novo e envie:

```bash
scripts/backup.sh
scp backups/financaplus_AAAA-MM-DD_HHMM.dump usuario@servidor:/var/www/html/financas-plus/backups/
```

No servidor, com o banco ainda vazio:

```bash
docker compose exec -T db sh -c 'pg_restore -U "$POSTGRES_USER" -d "$POSTGRES_DB" --no-owner' < backups/financaplus_AAAA-MM-DD_HHMM.dump
```

O dump traz as contas de usuário com as senhas atuais, então o login em produção é o mesmo do ambiente local. Não rode `db:seed`.

## 6. Subir tudo

```bash
docker compose up -d
docker compose exec app php artisan migrate --force
curl -I http://127.0.0.1:8088/up
```

O `migrate` não deve ter nada a aplicar se o dump for recente, e o `curl` deve responder `200`.

## 7. Domínio e HTTPS (Caddy)

Se o servidor ainda não tem o Caddy: `sudo apt install caddy`. Acrescente ao `/etc/caddy/Caddyfile`:

```
<seu-dominio> {
    encode gzip
    reverse_proxy 127.0.0.1:8088
}
```

```bash
sudo systemctl reload caddy
```

O Caddy emite e renova o certificado sozinho. Abra `https://<seu-dominio>` e entre com a conta de sempre.

## 8. Conferir o e-mail

```bash
docker compose exec app php artisan finance:testar-email voce@exemplo.com
```

## 9. Backup diário

`crontab -e` no servidor:

```
17 3 * * * cd /var/www/html/financas-plus && scripts/backup.sh >> backups/backup.log 2>&1
```

Os dumps ficam em `backups/` (os 14 mais recentes). Copie-os periodicamente para fora do servidor, por exemplo com `scp` ou `rclone`.

## Atualizar depois

**Automático:** todo push no `main` publica em produção pelo GitHub Actions ([.github/workflows/deploy.yml](.github/workflows/deploy.yml)). O andamento aparece na aba *Actions* do repositório; também dá para disparar à mão por lá (*Run workflow*).

**Manual**, no servidor (é o mesmo script que a pipeline roda):

```bash
cd /var/www/html/financas-plus && scripts/deploy.sh
```

O script faz backup do banco, `git pull`, build, recria `app`, `worker` e `scheduler`, roda as migrations e confere `/up`. Se o build falhar, os contêineres antigos continuam no ar. Mudanças no `.env` não passam pelo git: edite o arquivo no servidor e rode o script.

### Como a pipeline entra no servidor

O GitHub usa uma chave SSH própria, que no `~/.ssh/authorized_keys` do servidor tem comando forçado: com ela só é possível rodar `scripts/deploy.sh`, sem terminal nem encaminhamento de portas.

```
command="/var/www/html/financas-plus/scripts/deploy.sh",no-port-forwarding,no-agent-forwarding,no-X11-forwarding,no-pty ssh-ed25519 AAAA... glamfinancas-deploy
```

Segredos em *Settings → Secrets and variables → Actions*: `DEPLOY_SSH_KEY` (chave privada), `DEPLOY_HOST`, `DEPLOY_USER` e `DEPLOY_KNOWN_HOSTS` (saída de `ssh-keyscan -t ed25519 <servidor>`). Sem eles a pipeline só registra um aviso e não publica nada. Para desligar o deploy automático, apague a linha da chave no `authorized_keys`.

## Restaurar um backup em um banco que já tem dados

```bash
scripts/restore.sh backups/financaplus_AAAA-MM-DD_HHMM.dump
```

O script pede confirmação, faz um backup do estado atual e substitui os dados.

## Google Sheets (opcional)

A sincronização fica desligada. Para ligar em produção, envie `storage/app/google-service-account.json` para a mesma pasta no servidor (`sudo chown 82:82` no arquivo), preencha `GOOGLE_SHEETS_ID`, defina `GOOGLE_SHEETS_SYNC_ENABLED=true` e recrie `app`, `worker` e `scheduler`.

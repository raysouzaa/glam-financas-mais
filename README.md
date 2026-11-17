# GlamFinanças — Laravel + React/Inertia + PostgreSQL + Google Sheets

Aplicação web em português, responsiva, sem API Node separada. Esta é uma **versão inicial de desenvolvimento**; não foi conectada nem executada contra sua conta Google nesta entrega.

## Módulos incluídos

- Login de usuários (sessões do Laravel e proteção CSRF)
- Dashboard com receitas/despesas por competência, pessoa, categoria e projeção
- CRUD de pessoas (Vic, Ray e novos nomes), categorias, contas financeiras e cartões
- Lançamentos de receita e despesa com parcelamento exato em centavos
- Histórico de inclusão/alteração/exclusão e alterações importadas do Sheets
- Sincronização bidirecional com a **aba nova** `FinancaPlus_Lancamentos`
- Detecção e registro de conflitos quando a mesma compra mudou nos dois lados
- Importação explícita e idempotente das linhas A3:E da aba `Lançamentos` original

**IMPORTANTE:** o Google Sheets não é um banco transacional. O PostgreSQL é a fonte principal; o sincronizador é eventual (agendado a cada minuto). Não é garantido que um lançamento chegue à planilha no mesmo instante da gravação. Conflitos são preservados para revisão. Antes da implantação em produção, testar em uma cópia da planilha.

## Primeira inicialização

Requisitos: Docker Desktop + Docker Compose.

```bash
cp .env.example .env
# Edite DB_PASSWORD e APP_URL em .env
# No Mac/Linux execute:
mkdir -p storage/app storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs
docker compose build
docker compose up -d db app
docker compose exec app php artisan key:generate
docker compose up -d --force-recreate app
docker compose exec app php artisan migrate --force
docker compose exec app php artisan db:seed --force
docker compose up -d worker scheduler
docker compose exec app php artisan finance:create-admin seu-email@exemplo.com "Seu nome"
```

Acesse: **http://localhost:8088**.

### Importar dívidas do cartão

Menu **Importar dívidas** (só para administradores; `finance:admin email` concede o acesso). Envie a planilha `.xlsx` com a aba "Lançamentos" (Pessoa, Descrição, Valor Total da Compra, Parcelas, Mês Início). A prévia mostra as compras, os totais por pessoa e as parcelas por mês antes de gravar qualquer coisa; ao confirmar, cada compra vira uma despesa no cartão já dividida em parcelas. Dá para escolher o cartão e a categoria. Reenviar a planilha depois de acrescentar compras importa só as novas. Uma importação pode ser desfeita enquanto nenhuma parcela estiver marcada como paga.

Pelo terminal (mesmo processo): copie a planilha para `storage/app/` e rode `docker compose exec -u www-data app php artisan finance:importar-dividas storage/app/ARQUIVO.xlsx [--cartao="Nome"] [--categoria="Nome"] [--simular]`.

### Criar conta pela tela

Na tela de login há o botão **Criar conta** (nome completo, e-mail e senha com no mínimo 6 caracteres, contendo uma letra maiúscula, uma minúscula e um número). Depois do cadastro o app envia um e-mail com o link de confirmação (vale 60 minutos); só depois de confirmar é possível entrar. Na tela de login aparece "Reenviar e-mail de confirmação" se a conta ainda não foi confirmada. Contas criadas pelo `finance:create-admin` já nascem confirmadas.

**Atenção:** o app não separa dados por usuário. Toda conta criada vê e altera os mesmos lançamentos. Para limitar quem pode se cadastrar, use no `.env`:

- `REGISTRATION_ENABLED=false` desliga a tela de criar conta;
- `REGISTRATION_ALLOWED_EMAILS=voce@exemplo.com,outra@exemplo.com` aceita só esses e-mails.

**E-mail em desenvolvimento:** o serviço `mailpit` captura todos os e-mails enviados (nada chega a caixas reais). Veja-os em **http://localhost:8025**. Para entregar de verdade, rode `scripts/configurar-email.sh`: ele pede o provedor, o remetente e a senha/chave, grava no `.env`, reinicia `app` e `worker` e manda um e-mail de teste.

- **Resend (grátis até ~3.000 e-mails/mês, com domínio próprio, ex.: `nao-responda@vyrasolutions.com.br`):** crie a conta em resend.com, adicione o domínio em *Domains* e copie os registros DNS que ele mostrar para o Cloudflare (DNS → Records, com a nuvem cinza, "DNS only"). Quando o domínio aparecer como *Verified*, crie uma API key (*API Keys*, permissão "Sending access") e rode `scripts/configurar-email.sh resend`.
- **Gmail:** ligue a verificação em 2 etapas e crie uma senha de app em https://myaccount.google.com/apppasswords.

Para testar o envio a qualquer momento: `docker compose exec app php artisan finance:testar-email voce@exemplo.com`.

**Observação:** para a primeira carga, rode as migrations antes de liberar o worker/scheduler se você já ativou a integração do Google.

## Integrar Google Sheets

1. No Google Cloud, habilite a **Google Sheets API** e crie uma conta de serviço.
2. Baixe o JSON de credenciais; **nunca o envie ao Git**.
3. Salve o arquivo como `storage/app/google-service-account.json` (sem publicar no diretório `public`).
4. Compartilhe uma **cópia da planilha** com o e-mail da conta de serviço, permissão Editor.
5. Preencha o `GOOGLE_SHEETS_ID` no `.env` (parte entre `/d/` e `/edit` da URL).
6. Reinicie `app` e `scheduler` após alterar `.env`:

```bash
docker compose up -d --force-recreate app worker scheduler
docker compose exec app php artisan finance:sync-sheets
```

O sincronizador cria (se não existir) a aba `FinancaPlus_Lancamentos` **sem alterar a aba original**. Colunas: ID, Pessoa, Descrição, Valor (centavos), Parcelas, Mês início, Tipo, Categoria, Pagamento, Cartão, Conta, Observações, Excluído. Para apagar pelo Sheets, preencha `SIM` na coluna Excluído; **não remova linhas**. No Sheets, valores monetários desta aba são armazenados em centavos por precisão, enquanto a interface mostra reais.

Edições feitas diretamente no Sheets serão identificadas no próximo ciclo de sincronização. Não é possível identificar o editor humano apenas pela Sheets API de uma conta de serviço; o log marca `sheets` como origem. Divergências simultâneas geram `sync_conflicts`, visíveis em Auditoria, e requerem resolução manual; não são sobrescritas.

### Importar os lançamentos antigos

A aba original `Lançamentos` é preservada e não é editada pelo aplicativo. O importador aceita datas como serial numérico do Google Sheets, `dd/mm/aaaa` ou formatos reconhecidos pelo Carbon. Para copiar uma única vez os dados originais para o PostgreSQL:

```bash
docker compose exec app php artisan finance:import-legacy --dry-run
docker compose exec app php artisan finance:import-legacy
docker compose exec app php artisan finance:sync-sheets
```

A importação usa UUID determinístico por número de linha. Evite reordenar as linhas antigas antes de importar, e **não importe novamente linhas já importadas sob outra posição**, pois seriam tratadas como novos dados. As categorias/cartões não existentes nos dados antigos devem ser classificados depois no site.

## Regras importantes

- `people`: quem é responsável por uma compra; `financial_accounts`: conta bancária/carteira; `credit_cards`: cartão; `categories`: natureza da despesa ou receita.
- Lançamentos têm `amount_cents` inteiro; o parcelamento distribui os centavos remanescentes nas primeiras parcelas.
- Parcelas já marcadas como pagas bloqueiam alterações e exclusões das compras correspondentes.
- Exclusões são lógicas (`deleted_at`) e preservam histórico.
- `audit_logs` retém estado anterior e posterior (JSONB), usuário, origem, horário e IP para as operações expostas pela aplicação. `database_change_logs` usa triggers no PostgreSQL para registrar INSERT/UPDATE/DELETE mesmo fora da aplicação, mas operações SQL diretas não trazem a identidade do usuário do site.
- A planilha não substitui o banco nem garante transações atômicas com o PostgreSQL.
- Crie backup recorrente do PostgreSQL e proteja o acesso à planilha e às credenciais Google.

## Estrutura

`app/Http/Controllers`: login e módulos financeiros; `app/Models`: entidades; `app/Services`: auditoria, parcelas e sincronização; `app/Console/Commands`: cadastro administrativo, importação e sincronização; `resources/js/Pages`: telas React/Inertia; `database/migrations`: esquema PostgreSQL; `docker/`: infraestrutura Nginx/PHP-FPM.

## Para produção

O passo a passo de publicação (servidor com Docker, Caddy, envio do dump e backup) está em [DEPLOY.md](DEPLOY.md), e o modelo do `.env` de produção em `.env.producao.example`.

Antes de expor na internet: configurar HTTPS, `APP_DEBUG=false`, backups externos, monitoramento, permissões de usuários, testes end-to-end, políticas de privacidade e avaliação de segurança. O sistema foi gerado como base inicial, não homologado em ambiente real.

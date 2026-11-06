# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Visão geral

Finança+ é um app de finanças pessoais em português: Laravel 12 (PHP 8.4) + React 19/Inertia 2 (TypeScript, Tailwind 3, Chart.js) + PostgreSQL 16, com sincronização bidirecional com o Google Sheets. Não existe API separada: os controllers devolvem páginas Inertia. Interface, mensagens de validação e comentários ficam em português.

## Comandos

Tudo roda via Docker Compose (serviços `app` na porta 8088, `worker`, `scheduler`, `db`). **O código e os assets são copiados para a imagem no build** (o Dockerfile roda `npm run build` em um estágio Node). Só `./storage` e `./.env` são montados como volume, então qualquer mudança em PHP ou TSX exige rebuild:

```bash
docker compose build && docker compose up -d --force-recreate app worker scheduler
docker compose exec app php artisan migrate --force
docker compose exec app php artisan db:seed --force
docker compose exec app php artisan finance:create-admin email@exemplo.com "Nome"   # sem registro público; a senha é pedida interativamente (mín. 12 caracteres)
docker compose exec app php artisan finance:sync-sheets                             # roda a sincronização uma vez (o scheduler roda a cada minuto)
docker compose exec app php artisan finance:import-legacy --dry-run                 # importa a aba original "Lançamentos" A3:E
docker compose exec app ./vendor/bin/pint                                           # formatação PHP (única ferramenta de dev instalada)
```

Frontend local: `npm run dev` (Vite em 0.0.0.0:5173) / `npm run build` (gera `public/build`). Mudanças em `.env` exigem `--force-recreate` de `app`, `worker` e `scheduler`.

Ainda não há testes automatizados (nem PHPUnit, nem testes JS).

## Arquitetura

- **Backend concentrado em poucos arquivos.** As rotas ficam em [routes/web.php](routes/web.php) (caminhos em português: `/cadastros`, `/lancamentos`, `/parcelas`, `/auditoria`), e quase toda a lógica HTTP está em [FinanceController.php](app/Http/Controllers/FinanceController.php). Os cadastros de referência (pessoas, categorias, contas, cartões) usam rotas genéricas `/cadastros/{kind}`, com `kind` mapeado para o model por `model()` e as regras de validação por `fields()`. Para adicionar um cadastro novo, é preciso mexer nesses dois `match`, em `manage()` e no frontend.
- **Agendamento** fica em [bootstrap/app.php](bootstrap/app.php) (`finance:sync-sheets` a cada minuto, `withoutOverlapping`, e o comando também pega um `Cache::lock`). Não existe `app/Console/Kernel.php`.
- **Frontend:** [resources/js/app.tsx](resources/js/app.tsx) registra as páginas manualmente em um mapa (`Dashboard`, `Login`, `Manage`, `Audit`), então páginas novas precisam ser adicionadas ali. [Shell.tsx](resources/js/Components/Shell.tsx) contém o layout, a navegação e o helper `money()` (centavos → BRL). A página `Manage.tsx` serve todos os CRUDs, escolhidos pelo parâmetro `?kind=`.

### Invariantes do domínio

- **Dinheiro sempre em centavos inteiros** (`amount_cents`, `limit_cents`, `opening_balance_cents`). O formulário envia `amount` em reais e `transactionData()` converte para centavos. Na aba do Sheets o valor também fica em centavos.
- **Parcelas:** `InstallmentService::rebuild()` apaga e recria as parcelas, distribuindo o resto dos centavos nas primeiras. Ele lança `DomainException` se houver parcela paga: um lançamento com parcela paga não pode ser alterado nem excluído (nem pelo app, nem pelo Sheets). Receitas não podem ser parceladas. `start_month` e `due_month` são sempre normalizados para o dia 01.
- O dashboard agrega pela tabela **`installments` (`due_month`, regime de competência)**, não por `transactions`.
- IDs UUID (`HasUuids`) e soft delete (`deleted_at`) nas entidades financeiras.
- **Duas camadas de auditoria:** toda escrita feita pela aplicação deve chamar `Audit::write(action, entity, id, before, after, origin)` dentro da mesma `DB::transaction` (tabela `audit_logs`). Além disso, triggers do PostgreSQL (migration `..._000002`) gravam `database_change_logs` para as tabelas people, categories, financial_accounts, credit_cards, transactions e installments. Tabelas novas que precisem de auditoria devem ganhar o trigger.
- Coluna `origin` em transactions/audit: `app`, `sheets`, `legacy`/`import`.

### Sincronização com o Google Sheets ([GoogleSheetSync.php](app/Services/GoogleSheetSync.php))

O PostgreSQL é a fonte da verdade e a sincronização é eventual. Ela usa apenas a aba dedicada `FinancaPlus_Lancamentos` (A:M, 13 colunas fixas em `HEADER`); a aba original `Lançamentos` nunca é escrita. Funciona como um merge em três vias por hash SHA-256 da linha normalizada (`canonical()` para o lado local, `norm()` para o remoto), usando `sheet_sync_states.last_hash` como base:
- só o remoto mudou → aplica no banco (e recria as parcelas); só o local mudou → reescreve a linha; mudou nos dois lados, ID inválido ou duplicado, linha removida, linha inválida → grava em `sync_conflicts` (resolução manual, visível em Auditoria). Nada é sobrescrito automaticamente.
- Linha sem ID ganha um UUID novo; lançamento local sem estado de sincronização é anexado ao fim da aba. A exclusão pelo Sheets é feita com `SIM` na coluna "Excluído", nunca removendo a linha.
- Nomes de pessoa, categoria, cartão e conta vindos da planilha são resolvidos com `firstOrCreate` (restaurando registros com soft delete). Por isso, alterar `canonical()` ou a ordem das colunas muda todos os hashes e gera conflitos em massa.
- Cada execução grava uma linha em `sync_runs`.

O `finance:import-legacy` gera UUIDs determinísticos a partir do número da linha da planilha original: reordenar linhas antes de importar cria duplicatas.

## Configuração

Variáveis do Google em `config/services.php` (`GOOGLE_SHEETS_ID`, `GOOGLE_SHEETS_TAB`, `GOOGLE_APPLICATION_CREDENTIALS`). O JSON da conta de serviço fica em `storage/app/` (ignorado pelo git). Sessão, cache e fila usam o driver `database`.

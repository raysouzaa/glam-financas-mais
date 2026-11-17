# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Visão geral

GlamFinanças (antes chamado Finança+) é um app de finanças pessoais em português: Laravel 12 (PHP 8.4) + React 19/Inertia 2 (TypeScript, Tailwind 3, Chart.js) + PostgreSQL 16, com sincronização bidirecional com o Google Sheets. Não existe API separada: os controllers devolvem páginas Inertia. Interface, mensagens de validação e comentários ficam em português.

## Comandos

Tudo roda via Docker Compose (serviços `app` na porta 8088, `worker`, `scheduler`, `db` e `mailpit`, que captura os e-mails em http://localhost:8025). **O código e os assets são copiados para a imagem no build** (o Dockerfile roda `npm run build` em um estágio Node). Só `./storage` e `./.env` são montados como volume, então qualquer mudança em PHP ou TSX exige rebuild:

```bash
docker compose build && docker compose up -d --force-recreate app worker scheduler
docker compose exec app php artisan migrate --force
docker compose exec app php artisan db:seed --force                                 # pessoas, categorias e AdminSeeder (ADMIN_EMAIL/ADMIN_NAME/ADMIN_PASSWORD; senha vazia = gera e mostra uma vez; idempotente, nunca troca senha)
docker compose exec app php artisan finance:create-admin email@exemplo.com "Nome"   # a senha é pedida interativamente (mín. 6 caracteres, com maiúscula, minúscula e número; regra em app/Support/RegraSenha.php); já cria a conta com e-mail confirmado
docker compose exec app php artisan finance:sync-sheets                             # roda a sincronização uma vez (o scheduler roda a cada minuto)
docker compose exec app php artisan finance:import-legacy --dry-run                 # importa a aba original "Lançamentos" A3:E (Google Sheets)
docker compose exec -u www-data app php artisan finance:importar-dividas storage/app/X.xlsx --simular   # importa a planilha de dívidas do cartão (.xlsx)
docker compose exec app ./vendor/bin/pint                                           # formatação PHP (única ferramenta de dev instalada)
```

Frontend local: `npm run dev` (Vite em 0.0.0.0:5173) / `npm run build` (gera `public/build`). Mudanças em `.env` exigem `--force-recreate` de `app`, `worker` e `scheduler`.

Ainda não há testes automatizados (nem PHPUnit, nem testes JS).

## Arquitetura

- **Backend concentrado em poucos arquivos.** As rotas ficam em [routes/web.php](routes/web.php) (caminhos em português: `/cadastros`, `/lancamentos`, `/parcelas`, `/auditoria`), e quase toda a lógica HTTP está em [FinanceController.php](app/Http/Controllers/FinanceController.php). Os cadastros de referência (pessoas, categorias, contas, cartões) usam rotas genéricas `/cadastros/{kind}`, com `kind` mapeado para o model por `model()` e as regras de validação por `fields()`. Para adicionar um cadastro novo, é preciso mexer nesses dois `match`, em `manage()` e no frontend.
- **Agendamento** fica em [bootstrap/app.php](bootstrap/app.php) (`finance:sync-sheets` a cada minuto, `withoutOverlapping`, e o comando também pega um `Cache::lock`). Não existe `app/Console/Kernel.php`.
- **Frontend:** [resources/js/app.tsx](resources/js/app.tsx) registra as páginas manualmente em um mapa (`Dashboard`, `Login`, `Register`, `Manage`, `Audit`, `CardDebts`), então páginas novas precisam ser adicionadas ali. [Shell.tsx](resources/js/Components/Shell.tsx) contém o layout, a navegação e o helper `money()` (centavos → BRL). A página `Manage.tsx` serve todos os CRUDs, escolhidos pelo parâmetro `?kind=`. **Marca:** arquivos em `public/brand/` (logo horizontal, vertical, símbolo, ícone de app, `logo-email.png` e favicons; `public/favicon.ico` vem do símbolo). [Logo.tsx](resources/js/Components/Logo.tsx) escolhe a variante; cores azul `#1E4FD8`, azul claro `#5AA9FF` e marinho `#0B1F3A` (também na paleta `brand` do Tailwind). O e-mail de confirmação usa a view própria `resources/views/emails/confirmar-conta.blade.php` (logo embutida por CID, estilos inline) e não os templates padrão do Laravel. Os nomes internos (banco `financaplus`, aba `FinancaPlus_Lancamentos`, função `financaplus_capture_changes`) continuam com o nome antigo de propósito.

- **Criação de conta:** [RegisterController.php](app/Http/Controllers/RegisterController.php) cuida de `/criar-conta`, do link assinado `/email/confirmar/{id}/{hash}` (rota `verification.verify`, assinatura conferida no controller para redirecionar com mensagem em vez de 403) e de `/email/reenviar`. `User` implementa `MustVerifyEmail` e envia `App\Notifications\ConfirmarConta`. `AuthController::login` desloga e recusa contas sem `email_verified_at`. A chave `auth.registration` (`REGISTRATION_ENABLED`, `REGISTRATION_ALLOWED_EMAILS`) controla quem pode se cadastrar. Os dados financeiros **não** são separados por usuário: toda conta vê tudo. As strings em inglês dos templates de e-mail do Laravel são traduzidas em `lang/pt_BR.json`.

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

### Importação de dívidas do cartão ([CardDebtImportController.php](app/Http/Controllers/CardDebtImportController.php))

Tela `/admin/importar-dividas`, restrita a `users.is_admin` pelo middleware `EnsureAdmin` (`finance:create-admin` já cria a conta como admin; `finance:admin email [--remove]` concede ou remove). Lê a planilha "Dívidas do Cartão" (.xlsx, aba "Lançamentos": Pessoa, Descrição, Valor Total, Parcelas, Mês Início; colunas achadas pelo cabeçalho) com [CardDebtParser](app/Services/CardDebtParser.php) sobre o leitor sem dependências [XlsxReader](app/Services/XlsxReader.php). Fluxo: upload → prévia guardada na sessão (arquivo em `storage/app/private/imports/dividas-cartao`) → confirmação cria uma despesa `payment_method=credit_card`, `origin=legacy`, por compra, com parcelas via `InstallmentService`.
- O ID de cada lançamento é UUID v5 do conteúdo da linha (pessoa|descrição|centavos|parcelas|mês, mais o número da ocorrência), então reenviar a planilha atualizada importa só as compras novas. Mudar a fórmula do ID faz tudo parecer novo.
- Lançamentos já existentes (inclusive excluídos no app, com soft delete) são pulados. Pessoas são casadas pelo nome sem acento/maiúsculas; as que não existem são criadas.
- A gravação fica em [CardDebtImporter](app/Services/CardDebtImporter.php), usado pela tela e pelo comando `finance:importar-dividas arquivo [--cartao=] [--categoria=] [--simular]`.
- `card_debt_imports` / `card_debt_import_items` guardam cada importação. Desfazer apaga definitivamente (`forceDelete`) os lançamentos daquela importação, desde que nenhuma parcela esteja paga, para que possam ser importados de novo.

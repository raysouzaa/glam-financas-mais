<?php
namespace App\Console\Commands;

use App\Models\{Category,Person,User};
use App\Services\Audit;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Move todo o financeiro de uma conta para outra (pessoas, categorias, contas, cartões, lançamentos
 * e importações). Serve para entregar os dados que existiam antes do multiusuário à conta certa.
 */
class TransferirDados extends Command
{
    protected $signature = 'finance:transferir-dados {de : e-mail da conta que tem os dados hoje} {para : e-mail da conta que vai recebê-los} {--sim : não pede confirmação}';
    protected $description = 'Transfere todo o financeiro de uma conta para outra';

    private const TABELAS = ['people', 'categories', 'financial_accounts', 'credit_cards', 'transactions', 'card_debt_imports'];

    public function handle(): int
    {
        [$de, $para] = array_map(fn ($a) => User::where('email', Str::lower(trim($this->argument($a))))->first(), ['de', 'para']);
        if (! $de || ! $para) { $this->error('Conta não encontrada: confira os dois e-mails.'); return self::FAILURE; }
        if ($de->id === $para->id) { $this->error('As duas contas são a mesma.'); return self::FAILURE; }

        $contagem = [];
        foreach (self::TABELAS as $t) $contagem[$t] = DB::table($t)->where('user_id', $de->id)->count();
        $this->table(['Tabela', "Registros de {$de->name}"], array_map(fn ($t) => [$t, $contagem[$t]], self::TABELAS));
        if (! array_sum($contagem)) { $this->info('Nada para transferir.'); return self::SUCCESS; }
        if (! $this->option('sim') && ! $this->confirm("Transferir tudo para {$para->name} ({$para->email})?")) return self::FAILURE;

        try {
            DB::transaction(function () use ($de, $para, $contagem) {
                // Nome/CPF de pessoa e nome de categoria são únicos por conta: o que o destino já tem com o
                // mesmo nome (em geral as categorias padrão, ainda sem uso) sai para dar lugar ao que chega.
                $this->abrirEspaco(Person::class, $de->id, $para->id, fn ($p) => ['name' => $p->name], 'person_id', 'pessoa');
                $this->abrirEspaco(Person::class, $de->id, $para->id, fn ($p) => $p->cpf ? ['cpf' => $p->cpf] : null, 'person_id', 'pessoa');
                $this->abrirEspaco(Category::class, $de->id, $para->id, fn ($c) => ['name' => $c->name, 'type' => $c->type], 'category_id', 'categoria');

                foreach (self::TABELAS as $t) DB::table($t)->where('user_id', $de->id)->update(['user_id' => $para->id]);

                // Vínculos aceitos/removidos com a conta antiga passam a valer para a nova dona dos dados.
                DB::table('account_links')->where('owner_user_id', $de->id)
                    ->where(fn ($q) => $q->where('user_id', $para->id)->orWhereIn('user_id', DB::table('account_links')->where('owner_user_id', $para->id)->select('user_id')))->delete();
                DB::table('account_links')->where('owner_user_id', $de->id)->update(['owner_user_id' => $para->id]);

                Audit::write('transfer', 'users', (string) $de->id, ['user_id' => $de->id] + $contagem, ['user_id' => $para->id, 'email' => $para->email], 'system');
            });
        } catch (\DomainException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Transferido para {$para->name}.");
        if (config('services.google_sheets.spreadsheet_id')) $this->warn("Google Sheets: defina GOOGLE_SHEETS_OWNER_EMAIL={$para->email} no .env para a sincronização acompanhar a nova conta.");

        return self::SUCCESS;
    }

    /** Apaga do destino o registro que colide com um da origem, desde que nenhum lançamento o use. */
    private function abrirEspaco(string $model, int $de, int $para, \Closure $chave, string $fk, string $rotulo): void
    {
        foreach ($model::withTrashed()->where('user_id', $de)->get() as $origem) {
            $onde = $chave($origem);
            if (! $onde) continue;
            foreach ($model::withTrashed()->where('user_id', $para)->where($onde)->get() as $destino) {
                if (DB::table('transactions')->where($fk, $destino->id)->exists()) {
                    throw new \DomainException("A conta de destino já usa a {$rotulo} \"{$destino->name}\" em lançamentos. Renomeie-a e rode de novo.");
                }
                $destino->forceDelete();
            }
        }
    }
}

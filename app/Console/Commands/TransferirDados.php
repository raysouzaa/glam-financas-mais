<?php
namespace App\Console\Commands;

use App\Models\{Category,Person,User};
use App\Services\Audit;
use App\Support\CategoriasPadrao;
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

        DB::transaction(function () use ($de, $para, $contagem) {
                // Nome/CPF de pessoa e nome de categoria são únicos por conta: o que as duas contas têm com o
                // mesmo nome vira um só registro (o do destino), e os lançamentos que chegam passam a apontar para ele.
                $this->juntarRepetidos(Person::class, $de->id, $para->id, fn ($p) => ['name' => $p->name], [['transactions', 'person_id']]);
                $this->juntarRepetidos(Person::class, $de->id, $para->id, fn ($p) => $p->cpf ? ['cpf' => $p->cpf] : null, [['transactions', 'person_id']]);
                $this->juntarRepetidos(Category::class, $de->id, $para->id, fn ($c) => ['name' => $c->name, 'type' => $c->type], [['transactions', 'category_id'], ['card_debt_imports', 'category_id']]);

                foreach (self::TABELAS as $t) DB::table($t)->where('user_id', $de->id)->update(['user_id' => $para->id]);

                // Vínculos aceitos/removidos com a conta antiga passam a valer para a nova dona dos dados.
                DB::table('account_links')->where('owner_user_id', $de->id)
                    ->where(fn ($q) => $q->where('user_id', $para->id)->orWhereIn('user_id', DB::table('account_links')->where('owner_user_id', $para->id)->select('user_id')))->delete();
                DB::table('account_links')->where('owner_user_id', $de->id)->update(['owner_user_id' => $para->id]);

                // A conta de origem continua utilizável: volta a ter as categorias padrão.
                CategoriasPadrao::criarPara((int) $de->id);

                Audit::write('transfer', 'users', (string) $de->id, ['user_id' => $de->id] + $contagem, ['user_id' => $para->id, 'email' => $para->email], 'system');
            });

        $this->info("Transferido para {$para->name}.");
        if (config('services.google_sheets.spreadsheet_id')) $this->warn("Google Sheets: defina GOOGLE_SHEETS_OWNER_EMAIL={$para->email} no .env para a sincronização acompanhar a nova conta.");

        return self::SUCCESS;
    }

    /** Registro da origem que já existe no destino (mesma chave): as referências passam para o do destino e o da origem some. */
    private function juntarRepetidos(string $model, int $de, int $para, \Closure $chave, array $referencias): void
    {
        foreach ($model::withTrashed()->where('user_id', $de)->get() as $origem) {
            $onde = $chave($origem);
            if (! $onde) continue;
            $destino = $model::withTrashed()->where('user_id', $para)->where($onde)->first();
            if (! $destino) continue;
            foreach ($referencias as [$tabela, $coluna]) DB::table($tabela)->where($coluna, $origem->id)->update([$coluna => $destino->id]);
            if ($destino->trashed() && ! $origem->trashed()) $destino->restore();
            $cpf = $origem->cpf ?? null;
            $origem->forceDelete();
            if ($cpf && $model === Person::class && ! $destino->cpf) $destino->forceFill(['cpf' => $cpf])->save();
        }
    }
}

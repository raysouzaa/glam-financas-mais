<?php
namespace App\Console\Commands;

use App\Models\{Person,User};
use App\Services\Audit;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Junta duas pessoas da mesma conta: os lançamentos da primeira passam para a segunda e a primeira é apagada. */
class JuntarPessoas extends Command
{
    protected $signature = 'finance:juntar-pessoas {email : conta dona das pessoas} {de : nome da pessoa que some} {para : nome da pessoa que fica} {--sim : não pede confirmação}';
    protected $description = 'Passa os lançamentos de uma pessoa para outra da mesma conta e apaga a primeira';

    public function handle(): int
    {
        $dono = User::where('email', Str::lower(trim($this->argument('email'))))->first();
        if (! $dono) { $this->error('Conta não encontrada.'); return self::FAILURE; }
        [$de, $para] = array_map(fn ($a) => Person::where('user_id', $dono->id)->where('name', trim($this->argument($a)))->first(), ['de', 'para']);
        if (! $de || ! $para) { $this->error('Pessoa não encontrada nessa conta: confira os dois nomes.'); return self::FAILURE; }
        if ($de->id === $para->id) { $this->error('As duas pessoas são a mesma.'); return self::FAILURE; }
        if ($de->cpf && $para->cpf && $de->cpf !== $para->cpf) { $this->error('As duas pessoas têm CPFs diferentes; não parecem ser a mesma.'); return self::FAILURE; }

        $qtd = DB::table('transactions')->where('person_id', $de->id)->count();
        if (! $this->option('sim') && ! $this->confirm("Passar {$qtd} lançamento(s) de \"{$de->name}\" para \"{$para->name}\" e apagar \"{$de->name}\"?")) return self::FAILURE;

        DB::transaction(function () use ($dono, $de, $para, $qtd) {
            $antes = $de->toArray();
            DB::table('transactions')->where('person_id', $de->id)->update(['person_id' => $para->id, 'updated_at' => now()]);
            $cpf = $de->cpf;
            $de->forceDelete();
            if ($cpf && ! $para->cpf) $para->forceFill(['cpf' => $cpf])->save();
            Audit::write('merge', 'people', $para->id, $antes, ['merged_into' => $para->name, 'transactions' => $qtd, 'user_id' => $dono->id], 'system');
        });

        $this->info("{$qtd} lançamento(s) agora estão em \"{$para->name}\".");

        return self::SUCCESS;
    }
}

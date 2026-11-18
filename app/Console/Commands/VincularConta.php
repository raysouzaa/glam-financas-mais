<?php
namespace App\Console\Commands;

use App\Models\{Person,User};
use App\Services\Audit;
use App\Support\Cpf;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Vincula uma Pessoa de uma conta à conta de outro usuário, já com o vínculo aceito, a pedido dele.
 * Grava o CPF na pessoa (e na conta do usuário, se ela ainda não tiver) e cria o aceite em account_links.
 * O caminho normal continua sendo o usuário aceitar em Meu perfil; este comando é para o admin resolver direto.
 */
class VincularConta extends Command
{
    protected $signature = 'finance:vincular-conta {dono : e-mail da conta que tem a pessoa} {pessoa : nome da pessoa nessa conta} {usuario : e-mail da conta que passa a ver os lançamentos} {--cpf= : CPF do usuário, se a conta dele ainda não tiver}';
    protected $description = 'Vincula uma pessoa à conta de um usuário pelo CPF, com o vínculo já aceito';

    public function handle(): int
    {
        [$dono, $usuario] = array_map(fn ($a) => User::where('email', Str::lower(trim($this->argument($a))))->first(), ['dono', 'usuario']);
        if (! $dono || ! $usuario) { $this->error('Conta não encontrada: confira os dois e-mails.'); return self::FAILURE; }
        if ($dono->id === $usuario->id) { $this->error('A conta já é a dona desses lançamentos.'); return self::FAILURE; }
        $pessoa = Person::where('user_id', $dono->id)->where('name', trim($this->argument('pessoa')))->first();
        if (! $pessoa) { $this->error('Pessoa não encontrada nessa conta.'); return self::FAILURE; }

        $informado = Cpf::normalizar($this->option('cpf'));
        if ($informado !== null && ! Cpf::valido($informado)) { $this->error('CPF inválido.'); return self::FAILURE; }
        if ($usuario->cpf && $informado && $usuario->cpf !== $informado) { $this->error('A conta do usuário já tem outro CPF.'); return self::FAILURE; }
        $cpf = $usuario->cpf ?: $informado;
        if (! $cpf) { $this->error('A conta do usuário não tem CPF: informe com --cpf=.'); return self::FAILURE; }
        if (User::where('cpf', $cpf)->where('id', '!=', $usuario->id)->exists()) { $this->error('Este CPF já é de outra conta.'); return self::FAILURE; }
        if ($pessoa->cpf && $pessoa->cpf !== $cpf) { $this->error('A pessoa já está cadastrada com outro CPF.'); return self::FAILURE; }
        if (Person::withTrashed()->where('user_id', $dono->id)->where('cpf', $cpf)->where('id', '!=', $pessoa->id)->exists()) { $this->error('Essa conta já tem outra pessoa com este CPF.'); return self::FAILURE; }

        DB::transaction(function () use ($dono, $usuario, $pessoa, $cpf) {
            if (! $usuario->cpf) {
                $usuario->forceFill(['cpf' => $cpf])->save();
                Audit::write('update', 'users', (string) $usuario->id, ['cpf' => null], ['cpf' => Cpf::mascarar($cpf)], 'system');
            }
            if (! $pessoa->cpf) {
                $antes = $pessoa->toArray();
                $pessoa->forceFill(['cpf' => $cpf])->save();
                Audit::write('update', 'people', $pessoa->id, $antes, $pessoa->fresh()->toArray(), 'system');
            }
            $existe = DB::table('account_links')->where('owner_user_id', $dono->id)->where('user_id', $usuario->id)->exists();
            DB::table('account_links')->updateOrInsert(['owner_user_id' => $dono->id, 'user_id' => $usuario->id], ['status' => 'accepted', 'updated_at' => now()] + ($existe ? [] : ['created_at' => now()]));
            Audit::write('link_accepted', 'users', (string) $usuario->id, null, ['owner_user_id' => $dono->id, 'owner' => $dono->name, 'status' => 'accepted', 'por' => 'admin'], 'system');
        });

        $qtd = DB::table('transactions')->where('person_id', $pessoa->id)->whereNull('deleted_at')->count();
        $this->info("{$usuario->name} agora vê os {$qtd} lançamento(s) de \"{$pessoa->name}\" cadastrados por {$dono->name}.");

        return self::SUCCESS;
    }
}

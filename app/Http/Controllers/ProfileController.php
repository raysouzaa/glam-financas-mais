<?php
namespace App\Http\Controllers;

use App\Services\Audit;
use App\Support\Cpf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

/**
 * Meu perfil: dados da conta e os vínculos com contas que cadastraram uma Pessoa com o CPF do usuário.
 * Aceitar um vínculo libera (somente leitura) tudo o que aquele dono lançar nessa pessoa, agora e depois;
 * remover só esconde os lançamentos para o usuário, sem mexer nos dados de quem cadastrou.
 */
class ProfileController
{
    public function page(Request $r)
    {
        $u = $r->user();

        return Inertia::render('Profile', [
            'conta' => ['name' => $u->name, 'email' => $u->email, 'cpf' => Cpf::formatar($u->cpf)],
            'vinculos' => $u->vinculos()->map(fn ($v) => [
                'owner_id' => $v->owner_id, 'owner_name' => $v->owner_name, 'person_name' => $v->person_name,
                'compras' => (int) $v->compras, 'status' => $v->status,
            ])->values(),
        ]);
    }

    public function aceitar(Request $r, int $dono)
    {
        $v = $this->decidir($r, $dono, 'accepted');

        return back()->with('success', "Vínculo aceito. Os lançamentos que {$v->owner_name} cadastrar no seu CPF aparecem junto dos seus.");
    }

    public function remover(Request $r, int $dono)
    {
        $v = $this->decidir($r, $dono, 'removed');

        return back()->with('success', "Vínculo removido. Os lançamentos de {$v->owner_name} deixam de aparecer para você; os dados dele(a) continuam como estão.");
    }

    private function decidir(Request $r, int $dono, string $status): object
    {
        $u = $r->user();
        // Só existe vínculo a decidir se aquela conta realmente cadastrou uma pessoa com o CPF do usuário.
        $v = $u->vinculos()->firstWhere('owner_id', $dono);
        abort_unless($v, 404);

        DB::transaction(function () use ($u, $v, $status) {
            DB::table('account_links')->updateOrInsert(
                ['owner_user_id' => $v->owner_id, 'user_id' => $u->id],
                ['status' => $status, 'updated_at' => now()] + ($v->status === 'pending' ? ['created_at' => now()] : []),
            );
            Audit::write($status === 'accepted' ? 'link_accepted' : 'link_removed', 'users', (string) $u->id, ['owner_user_id' => $v->owner_id, 'status' => $v->status], ['owner_user_id' => $v->owner_id, 'owner' => $v->owner_name, 'status' => $status]);
        });
        $u->esquecerVinculos();

        return $v;
    }
}

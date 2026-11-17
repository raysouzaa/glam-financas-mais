<?php
namespace App\Models;

use App\Notifications\ConfirmarConta;
use App\Notifications\RedefinirSenha;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class User extends Authenticatable implements MustVerifyEmail
{
    use Notifiable;

    protected $fillable = ['name', 'email', 'cpf', 'password'];
    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return ['password' => 'hashed', 'email_verified_at' => 'datetime', 'is_admin' => 'boolean'];
    }

    private ?Collection $vinculosCache = null;

    /**
     * Contas que cadastraram uma Pessoa com o CPF deste usuário, com a decisão dele sobre cada uma:
     * pending (ainda não respondeu), accepted (vê os lançamentos daquela pessoa) ou removed.
     * O aceite vale por dono: depois de aceito, tudo o que o dono lançar nessa pessoa aparece sozinho.
     */
    public function vinculos(): Collection
    {
        if ($this->vinculosCache !== null) return $this->vinculosCache;
        if (! $this->cpf) return $this->vinculosCache = collect();

        return $this->vinculosCache = DB::table('people as p')
            ->join('users as o', 'o.id', '=', 'p.user_id')
            ->leftJoin('account_links as l', fn ($j) => $j->on('l.owner_user_id', '=', 'p.user_id')->where('l.user_id', $this->id))
            ->where('p.cpf', $this->cpf)->whereNull('p.deleted_at')->where('p.user_id', '!=', $this->id)
            ->orderBy('o.name')
            ->selectRaw("o.id as owner_id, o.name as owner_name, p.id as person_id, p.name as person_name, COALESCE(l.status, 'pending') as status, l.updated_at as decided_at, (SELECT COUNT(*) FROM transactions t WHERE t.person_id = p.id AND t.deleted_at IS NULL) as compras")
            ->get();
    }

    /** IDs das Pessoas (cadastradas por outras contas, com o CPF deste usuário) cujo vínculo ele aceitou. */
    public function pessoasVinculadas(): array
    {
        return $this->vinculos()->where('status', 'accepted')->pluck('person_id')->all();
    }

    public function esquecerVinculos(): void
    {
        $this->vinculosCache = null;
    }

    /** Dono dos dados nos processos sem usuário logado (Google Sheets, importações pelo terminal). */
    public static function donoPadrao(?string $email = null): ?self
    {
        $email = Str::lower(trim((string) ($email ?: config('services.google_sheets.owner_email'))));
        if ($email !== '') return self::where('email', $email)->first();

        return self::where('is_admin', true)->orderBy('id')->first();
    }

    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new ConfirmarConta);
    }

    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new RedefinirSenha($token));
    }
}

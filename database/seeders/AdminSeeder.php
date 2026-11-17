<?php
namespace Database\Seeders;

use App\Models\User;
use App\Services\Audit;
use App\Support\RegraSenha;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Garante uma conta administradora (is_admin + e-mail confirmado).
 * Configuração em config/auth.php → admin (ADMIN_EMAIL, ADMIN_NAME, ADMIN_PASSWORD).
 * Idempotente: se a conta já existe, só promove a admin e confirma o e-mail; a senha não é alterada.
 */
class AdminSeeder extends Seeder
{
    public function run(): void
    {
        $email = Str::lower(trim((string) config('auth.admin.email')));
        if ($email === '') {
            if (app()->isProduction()) {
                $this->command?->warn('AdminSeeder: defina ADMIN_EMAIL no .env para criar a conta administradora.');
                return;
            }
            $email = 'admin@financaplus.local';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->command?->error("AdminSeeder: ADMIN_EMAIL inválido ({$email}).");
            return;
        }

        if ($user = User::where('email', $email)->first()) {
            $user->forceFill(['is_admin' => true, 'email_verified_at' => $user->email_verified_at ?? now()])->save();
            $this->command?->info("AdminSeeder: {$email} já existia; agora é administrador (senha mantida).");
            return;
        }

        $password = (string) config('auth.admin.password');
        $generated = $password === '';
        if ($generated) {
            $password = $this->strongPassword();
        } elseif ($erros = RegraSenha::erros($password)) {
            $this->command?->error('AdminSeeder: ADMIN_PASSWORD fraca. '.RegraSenha::mensagem($erros));
            return;
        }

        $user = User::create(['email' => $email, 'name' => (string) config('auth.admin.name', 'Administrador'), 'password' => $password]);
        $user->forceFill(['is_admin' => true, 'email_verified_at' => now()])->save();
        Audit::write('create', 'users', (string) $user->id, null, ['email' => $email, 'is_admin' => true], 'system');

        $this->command?->info("AdminSeeder: conta administradora criada para {$email}.");
        if ($generated) {
            $this->command?->warn("Senha gerada (anote agora, ela não será mostrada de novo): {$password}");
        }
    }

    /** Senha aleatória que sempre cumpre a RegraSenha (maiúscula, minúscula e número). */
    private function strongPassword(): string
    {
        $pick = fn(string $chars) => $chars[random_int(0, strlen($chars) - 1)];
        $upper = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
        $lower = 'abcdefghijkmnopqrstuvwxyz';
        $digits = '23456789';
        $all = $upper.$lower.$digits;
        $chars = [$pick($upper), $pick($lower), $pick($digits)];
        while (count($chars) < 16) $chars[] = $pick($all);
        shuffle($chars);
        return implode('', $chars);
    }
}

<?php
namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Audit;
use App\Support\CategoriasPadrao;
use App\Support\Cpf;
use App\Support\RegraSenha;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;

/**
 * Criação de conta pelo próprio usuário: cadastro → e-mail de confirmação → login.
 * A conta só consegue entrar depois de confirmar o e-mail (ver AuthController::login).
 */
class RegisterController
{
    private const MSG_ENVIADO = 'Se houver uma conta aguardando confirmação para este e-mail, enviamos um novo link. Confira sua caixa de entrada e o spam.';

    public function page()
    {
        abort_unless(config('auth.registration.enabled'), 404);

        return Inertia::render('Register');
    }

    public function store(Request $request)
    {
        abort_unless(config('auth.registration.enabled'), 404);

        $request->merge(['email' => Str::lower(trim((string) $request->input('email'))), 'cpf' => Cpf::normalizar($request->input('cpf'))]);
        $data = $request->validate([
            'name' => 'required|string|min:3|max:255',
            'email' => 'required|email|max:255|unique:users,email',
            // O CPF liga a conta às Pessoas que outros usuários cadastrarem com ele (vínculo aceito no perfil).
            'cpf' => ['required', 'unique:users,cpf', function (string $attr, mixed $value, \Closure $fail) {
                if (! Cpf::valido((string) $value)) $fail('Informe um CPF válido.');
            }],
            'password' => ['required', 'string', 'max:255', 'confirmed', function (string $attr, mixed $value, \Closure $fail) {
                if ($erros = RegraSenha::erros((string) $value)) $fail(RegraSenha::mensagem($erros));
            }],
        ], [
            'name.required' => 'Informe seu nome completo.',
            'name.min' => 'Informe seu nome completo.',
            'name.max' => 'O nome pode ter no máximo 255 caracteres.',
            'email.required' => 'Informe seu e-mail.',
            'email.email' => 'Informe um e-mail válido.',
            'email.max' => 'O e-mail pode ter no máximo 255 caracteres.',
            'email.unique' => 'Já existe uma conta com este e-mail.',
            'cpf.required' => 'Informe seu CPF.',
            'cpf.unique' => 'Já existe uma conta com este CPF.',
            'password.required' => 'Crie uma senha.',
            'password.max' => 'A senha pode ter no máximo 255 caracteres.',
            'password.confirmed' => 'As senhas não conferem.',
        ]);

        $permitidos = config('auth.registration.allowed_emails');
        if ($permitidos && ! in_array($data['email'], $permitidos, true)) {
            return back()->withErrors(['email' => 'Este e-mail não está autorizado a criar conta.']);
        }

        $user = DB::transaction(function () use ($data) {
            $user = User::create(['name' => trim($data['name']), 'email' => $data['email'], 'cpf' => $data['cpf'], 'password' => $data['password']]);
            CategoriasPadrao::criarPara((int) $user->id);
            Audit::write('register', 'users', (string) $user->id, null, ['name' => $user->name, 'email' => $user->email, 'cpf' => Cpf::mascarar($user->cpf)]);

            return $user;
        });

        try {
            $user->sendEmailVerificationNotification();
        } catch (\Throwable $e) {
            report($e);

            return redirect()->route('login')->with('error', 'Sua conta foi criada, mas não conseguimos enviar o e-mail de confirmação agora. Tente "Reenviar e-mail de confirmação" daqui a pouco.');
        }

        return redirect()->route('login')->with('success', "Conta criada! Enviamos um link de confirmação para {$user->email}. Confirme o e-mail para poder entrar.");
    }

    public function verify(Request $request, string $id, string $hash)
    {
        if (! $request->hasValidSignature()) {
            return redirect()->route('login')->with('error', 'Este link de confirmação é inválido ou expirou. Peça um novo em "Reenviar e-mail de confirmação".');
        }

        $user = User::find($id);
        if (! $user || ! hash_equals(sha1($user->getEmailForVerification()), $hash)) {
            return redirect()->route('login')->with('error', 'Este link de confirmação é inválido.');
        }

        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
            Audit::write('email_verified', 'users', (string) $user->id, null, ['email' => $user->email]);
        }

        return redirect()->route('login')->with('success', 'E-mail confirmado! Agora é só entrar com seu e-mail e senha.');
    }

    public function resend(Request $request)
    {
        $request->merge(['email' => Str::lower(trim((string) $request->input('email')))]);
        $data = $request->validate(['email' => 'required|email'], [
            'email.required' => 'Informe seu e-mail.',
            'email.email' => 'Informe um e-mail válido.',
        ]);

        $user = User::where('email', $data['email'])->first();
        if ($user && ! $user->hasVerifiedEmail()) {
            try {
                $user->sendEmailVerificationNotification();
            } catch (\Throwable $e) {
                report($e);

                return back()->with('error', 'Não conseguimos enviar o e-mail agora. Tente novamente em alguns minutos.');
            }
        }

        // Mesma resposta exista ou não a conta, para não revelar quais e-mails estão cadastrados.
        return back()->with('success', self::MSG_ENVIADO);
    }
}

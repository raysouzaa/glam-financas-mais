<?php
namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Audit;
use App\Support\Cpf;
use App\Support\RegraSenha;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Inertia\Inertia;

/**
 * "Esqueci minha senha": a pessoa se identifica pelo e-mail ou pelo CPF.
 * - E-mail: o link vai direto para ele (se existir conta).
 * - CPF: a tela pede para ela digitar o e-mail da conta; o link só sai se CPF e e-mail forem da mesma conta.
 * O fluxo nunca mostra o e-mail nem o CPF cadastrados e responde sempre igual, exista a conta ou não.
 */
class PasswordResetController
{
    private const MSG_ENVIADO = 'Se os dados conferirem com uma conta, enviamos um link para criar uma nova senha. Confira sua caixa de entrada e o spam.';
    private const SESSAO_CPF = 'reset_senha_cpf';

    public function page(Request $request)
    {
        return Inertia::render('ForgotPassword', ['etapa' => $request->session()->has(self::SESSAO_CPF) ? 'confirmar' : 'identificar']);
    }

    public function identify(Request $request)
    {
        $request->session()->forget(self::SESSAO_CPF);
        $valor = trim((string) $request->input('login'));
        if ($valor === '') return back()->withErrors(['login' => 'Informe seu e-mail ou CPF.']);

        if (str_contains($valor, '@')) {
            $email = Str::lower($valor);
            if (! filter_var($email, FILTER_VALIDATE_EMAIL)) return back()->withErrors(['login' => 'Informe um e-mail ou CPF válido.']);
            $this->enviar(User::where('email', $email)->first(), 'email');

            return redirect()->route('login')->with('success', self::MSG_ENVIADO);
        }

        if (! Cpf::valido($valor)) return back()->withErrors(['login' => 'Informe um e-mail ou CPF válido.']);

        // Segue para a confirmação do e-mail mesmo que o CPF não exista, para não revelar quem tem conta.
        $request->session()->put(self::SESSAO_CPF, Cpf::normalizar($valor));

        return redirect()->route('password.request');
    }

    public function confirm(Request $request)
    {
        $cpf = $request->session()->get(self::SESSAO_CPF);
        if (! $cpf) return redirect()->route('password.request');

        $request->merge(['email' => Str::lower(trim((string) $request->input('email')))]);
        $request->validate(['email' => 'required|email'], [
            'email.required' => 'Digite o e-mail cadastrado na sua conta.',
            'email.email' => 'Informe um e-mail válido.',
        ]);

        $request->session()->forget(self::SESSAO_CPF);
        $this->enviar(User::where('cpf', $cpf)->where('email', $request->input('email'))->first(), 'cpf');

        return redirect()->route('login')->with('success', self::MSG_ENVIADO);
    }

    public function cancel(Request $request)
    {
        $request->session()->forget(self::SESSAO_CPF);

        return redirect()->route('password.request');
    }

    public function resetPage(Request $request, string $token)
    {
        return Inertia::render('ResetPassword', ['token' => $token, 'email' => (string) $request->query('email')]);
    }

    public function reset(Request $request)
    {
        $request->merge(['email' => Str::lower(trim((string) $request->input('email')))]);
        $data = $request->validate([
            'token' => 'required|string',
            'email' => 'required|email',
            'password' => ['required', 'string', 'max:255', 'confirmed', function (string $attr, mixed $value, \Closure $fail) {
                if ($erros = RegraSenha::erros((string) $value)) $fail(RegraSenha::mensagem($erros));
            }],
        ], [
            'token.required' => 'Link inválido. Peça um novo em "Esqueci minha senha".',
            'email.required' => 'Link inválido. Peça um novo em "Esqueci minha senha".',
            'email.email' => 'Link inválido. Peça um novo em "Esqueci minha senha".',
            'password.required' => 'Crie uma nova senha.',
            'password.max' => 'A senha pode ter no máximo 255 caracteres.',
            'password.confirmed' => 'As senhas não conferem.',
        ]);

        $status = Password::broker()->reset($data, function (User $user, string $senha) {
            DB::transaction(function () use ($user, $senha) {
                $user->forceFill(['password' => $senha, 'remember_token' => Str::random(60)]);
                // Abrir o link que chegou no e-mail também prova que o e-mail é da pessoa.
                if (! $user->hasVerifiedEmail()) $user->email_verified_at = now();
                $user->save();
                // Derruba as sessões abertas com a senha antiga.
                DB::table('sessions')->where('user_id', $user->id)->delete();
                Audit::write('password_reset', 'users', (string) $user->id, null, ['email' => $user->email]);
            });
        });

        if ($status !== Password::PASSWORD_RESET) {
            return back()->withErrors(['password' => 'Este link é inválido ou expirou. Peça um novo em "Esqueci minha senha".']);
        }

        return redirect()->route('login')->with('success', 'Senha alterada! Agora é só entrar com seu e-mail e a nova senha.');
    }

    private function enviar(?User $user, string $via): void
    {
        if (! $user) return;
        try {
            // O broker já limita um pedido por minuto por conta (auth.passwords.users.throttle).
            $status = Password::broker()->sendResetLink(['email' => $user->email]);
            if ($status === Password::RESET_LINK_SENT) Audit::write('password_reset_requested', 'users', (string) $user->id, null, ['via' => $via]);
        } catch (\Throwable $e) {
            // Não muda a resposta para não revelar que a conta existe.
            report($e);
        }
    }
}

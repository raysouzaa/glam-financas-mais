<?php
namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * E-mail de "Esqueci minha senha", com o visual da marca
 * (resources/views/emails/redefinir-senha.blade.php + versão em texto).
 * O link leva para a rota `password.reset` e expira em `auth.passwords.users.expire` minutos.
 */
class RedefinirSenha extends ResetPassword
{
    public function toMail($notifiable): MailMessage
    {
        $app = config('app.name');
        $assunto = "Redefina sua senha no {$app}";

        return (new MailMessage)
            ->subject($assunto)
            ->view(['emails.redefinir-senha', 'emails.redefinir-senha-texto'], [
                'assunto' => $assunto,
                'app' => $app,
                'nome' => $notifiable->name,
                'url' => $this->resetUrl($notifiable),
                'minutos' => config('auth.passwords.users.expire', 60),
            ]);
    }

    protected function resetUrl($notifiable): string
    {
        return route('password.reset', ['token' => $this->token, 'email' => $notifiable->getEmailForPasswordReset()]);
    }
}

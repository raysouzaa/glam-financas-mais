<?php
namespace App\Notifications;

use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * E-mail de confirmação de conta, com o visual da marca
 * (resources/views/emails/confirmar-conta.blade.php + versão em texto).
 * O link é a rota assinada `verification.verify` (gerada pela classe pai)
 * e expira em `auth.verification.expire` minutos.
 */
class ConfirmarConta extends VerifyEmail
{
    public function toMail($notifiable): MailMessage
    {
        $app = config('app.name');
        $assunto = "Confirme sua conta no {$app}";

        return (new MailMessage)
            ->subject($assunto)
            ->view(['emails.confirmar-conta', 'emails.confirmar-conta-texto'], [
                'assunto' => $assunto,
                'app' => $app,
                'nome' => $notifiable->name,
                'url' => $this->verificationUrl($notifiable),
                'minutos' => config('auth.verification.expire', 60),
            ]);
    }
}

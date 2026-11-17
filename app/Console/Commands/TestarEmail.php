<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class TestarEmail extends Command
{
    protected $signature = 'finance:testar-email {para : e-mail que vai receber o teste}';
    protected $description = 'Envia um e-mail de teste com a configuração MAIL_* atual';

    public function handle(): int
    {
        $para = $this->argument('para');
        $this->line('Servidor: '.config('mail.mailers.smtp.host').':'.config('mail.mailers.smtp.port').' | remetente: '.config('mail.from.address'));
        try {
            Mail::raw('Se você recebeu esta mensagem, o envio de e-mails do '.config('app.name').' está funcionando.', function ($m) use ($para) {
                $m->to($para)->subject('Teste de e-mail do '.config('app.name'));
            });
        } catch (\Throwable $e) {
            $this->error('Falhou: '.$e->getMessage());
            if (str_contains($e->getMessage(), '535')) {
                $this->warn('O servidor recusou o login: confira o e-mail e a senha (no Gmail, use a senha de app, não a senha normal).');
            }

            return self::FAILURE;
        }
        $this->info("Enviado para {$para}. Confira a caixa de entrada (e o spam).");

        return self::SUCCESS;
    }
}

<?php
return [
 'defaults'=>['guard'=>'web','passwords'=>'users'],
 'guards'=>['web'=>['driver'=>'session','provider'=>'users']],
 'providers'=>['users'=>['driver'=>'eloquent','model'=>App\Models\User::class]],
 'passwords'=>['users'=>['provider'=>'users','table'=>'password_reset_tokens','expire'=>60,'throttle'=>60]],
 'password_timeout'=>10800,
 // Validade (minutos) do link do e-mail de confirmação de conta.
 'verification'=>['expire'=>(int)env('EMAIL_VERIFICATION_EXPIRE',60)],
 // Criação de conta pela tela "Criar conta". Atenção: todas as contas veem os mesmos dados financeiros.
 // REGISTRATION_ALLOWED_EMAILS (opcional): lista separada por vírgula; vazia = qualquer e-mail pode se cadastrar.
 // Conta administradora criada pelo AdminSeeder (php artisan db:seed). Senha vazia = gera uma aleatória e mostra no terminal.
 'admin'=>['email'=>env('ADMIN_EMAIL'),'name'=>env('ADMIN_NAME','Administrador'),'password'=>env('ADMIN_PASSWORD')],
 'registration'=>[
  'enabled'=>(bool)env('REGISTRATION_ENABLED',true),
  'allowed_emails'=>array_values(array_filter(array_map(fn($e)=>strtolower(trim($e)),explode(',',(string)env('REGISTRATION_ALLOWED_EMAILS',''))))),
 ],
];

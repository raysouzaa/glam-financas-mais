<?php
namespace App\Console\Commands;
use App\Models\User;
use App\Support\RegraSenha;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
class CreateAdmin extends Command {
 protected $signature='finance:create-admin {email} {name}';protected $description='Cria conta administrativa com senha digitada interativamente (já com e-mail confirmado)';
 public function handle():int{
  $email=Str::lower(trim($this->argument('email')));if(!filter_var($email,FILTER_VALIDATE_EMAIL)){$this->error('E-mail inválido');return self::FAILURE;}
  if(User::where('email',$email)->exists()){$this->error('Este e-mail já existe');return self::FAILURE;}
  $password=(string)$this->secret('Digite a senha (mín. '.RegraSenha::MIN.' caracteres, com maiúscula, minúscula e número)');if($erros=RegraSenha::erros($password)){$this->error(RegraSenha::mensagem($erros));return self::FAILURE;}
  $user=User::create(['email'=>$email,'name'=>$this->argument('name'),'password'=>$password]);$user->forceFill(['is_admin'=>true])->save();$user->markEmailAsVerified();\App\Support\CategoriasPadrao::criarPara((int)$user->id);$this->info('Usuário criado.');return self::SUCCESS;
 }
}

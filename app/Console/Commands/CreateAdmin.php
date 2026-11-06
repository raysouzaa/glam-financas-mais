<?php
namespace App\Console\Commands;
use App\Models\User;
use Illuminate\Console\Command;
class CreateAdmin extends Command {
 protected $signature='finance:create-admin {email} {name}';protected $description='Cria conta administrativa com senha digitada interativamente';
 public function handle():int{
  $email=$this->argument('email');if(!filter_var($email,FILTER_VALIDATE_EMAIL)){$this->error('E-mail inválido');return self::FAILURE;}
  if(User::where('email',$email)->exists()){$this->error('Este e-mail já existe');return self::FAILURE;}
  $password=$this->secret('Digite a senha (mínimo de 12 caracteres)');if(strlen((string)$password)<12){$this->error('Senha insuficiente');return self::FAILURE;}
  User::create(['email'=>$email,'name'=>$this->argument('name'),'password'=>$password]);$this->info('Usuário criado.');return self::SUCCESS;
 }
}

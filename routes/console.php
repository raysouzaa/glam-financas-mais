<?php
use Illuminate\Support\Facades\Artisan;
Artisan::command('finance:status',function(){ $this->info('GlamFinanças ativo'); });
Artisan::command('finance:admin {email} {--remove : Remove o acesso de administrador}',function(string $email){
 $user=\App\Models\User::where('email',\Illuminate\Support\Str::lower(trim($email)))->first();
 if(!$user){$this->error('Usuário não encontrado');return 1;}
 $user->forceFill(['is_admin'=>!$this->option('remove')])->save();
 $this->info($user->is_admin?'Agora é administrador.':'Acesso de administrador removido.');return 0;
})->purpose('Concede ou remove o acesso de administrador de um usuário');

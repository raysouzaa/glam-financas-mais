<?php
namespace Database\Seeders;
use App\Models\{Person,User};
use App\Services\Accounts;
use App\Support\Tenant;
use Illuminate\Database\Seeder;
class DatabaseSeeder extends Seeder {public function run():void {
 $users=User::orderBy('id')->get();
 // Sem usuários, os registros ficam sem dono e são herdados pelo primeiro usuário criado.
 Tenant::run($users->first()?->id,function(){foreach(['Vic','Ray'] as $name)Person::withTrashed()->firstOrCreate(['name'=>$name]);});
 if($users->isEmpty())Accounts::defaults(new User());
 foreach($users as $user)Accounts::defaults($user);
}}

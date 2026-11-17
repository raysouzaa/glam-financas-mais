<?php
namespace Database\Seeders;
use App\Models\{Person,Category};
use Illuminate\Database\Seeder;
class DatabaseSeeder extends Seeder {public function run():void {
 foreach(['Vic','Ray'] as $name) Person::firstOrCreate(['name'=>$name]);
 foreach(['Alimentação','Supermercado','Transporte','Combustível','Moradia','Saúde','Educação','Compras','Lazer','Assinaturas','Viagens','Outros'] as $name) Category::firstOrCreate(['name'=>$name,'type'=>'expense'],['color'=>'#2d7abe']);
 foreach(['Salário','Pensão','Renda extra','PJ / Prestação de serviços','Aluguel recebido','Reembolso','Rendimentos','Outras receitas'] as $name) Category::firstOrCreate(['name'=>$name,'type'=>'income'],['color'=>'#16a34a']);
 $this->call(AdminSeeder::class);
}}

<?php
namespace Database\Seeders;
use Illuminate\Database\Seeder;
/** Só garante a conta administradora. Pessoas e categorias são de cada conta (categorias padrão: App\Support\CategoriasPadrao). */
class DatabaseSeeder extends Seeder {public function run():void {
 $this->call(AdminSeeder::class);
}}

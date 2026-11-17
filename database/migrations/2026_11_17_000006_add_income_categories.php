<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Tipos de receita usados no modal de lançamento (só cria os que ainda não existem; restaura os excluídos). */
return new class extends Migration {
    public const RECEITAS = ['Salário', 'Pensão', 'Renda extra', 'PJ / Prestação de serviços', 'Aluguel recebido', 'Reembolso', 'Rendimentos', 'Outras receitas'];

    public function up(): void
    {
        foreach (self::RECEITAS as $nome) {
            $existe = DB::table('categories')->where('type', 'income')->where('name', $nome)->first();
            if ($existe) {
                if ($existe->deleted_at) DB::table('categories')->where('id', $existe->id)->update(['deleted_at' => null, 'updated_at' => now()]);
                continue;
            }
            DB::table('categories')->insert(['id' => (string) Str::uuid(), 'name' => $nome, 'type' => 'income', 'color' => '#16a34a', 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        // Não remove: as categorias podem já estar em uso por lançamentos.
    }
};

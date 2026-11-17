<?php
namespace App\Support;

use App\Models\Category;

/** Categorias com que toda conta nova começa (o usuário pode editar ou apagar depois). */
class CategoriasPadrao
{
    public const DESPESAS = ['Alimentação', 'Supermercado', 'Transporte', 'Combustível', 'Moradia', 'Saúde', 'Educação', 'Compras', 'Lazer', 'Assinaturas', 'Viagens', 'Outros'];
    public const RECEITAS = ['Salário', 'Pensão', 'Renda extra', 'PJ / Prestação de serviços', 'Aluguel recebido', 'Reembolso', 'Rendimentos', 'Outras receitas'];

    /** Idempotente: só cria as que a conta ainda não tem (nem apagadas). */
    public static function criarPara(int $userId): void
    {
        foreach ([['expense', self::DESPESAS, '#2d7abe'], ['income', self::RECEITAS, '#16a34a']] as [$tipo, $nomes, $cor]) {
            foreach ($nomes as $nome) {
                Category::withTrashed()->firstOrCreate(['user_id' => $userId, 'name' => $nome, 'type' => $tipo], ['color' => $cor]);
            }
        }
    }
}

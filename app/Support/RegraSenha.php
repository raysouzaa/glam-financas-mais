<?php
namespace App\Support;

/**
 * Regra única de senha (tela de criar conta e finance:create-admin).
 * O mesmo conjunto de requisitos aparece como checklist em resources/js/Pages/Register.tsx.
 */
class RegraSenha
{
    public const MIN = 6;

    /** @return string[] mensagens dos requisitos que a senha não cumpre (vazio = senha válida) */
    public static function erros(string $senha): array
    {
        $erros = [];
        if (mb_strlen($senha) < self::MIN) $erros[] = 'ter pelo menos '.self::MIN.' caracteres';
        if (! preg_match('/\p{Lu}/u', $senha)) $erros[] = 'ter uma letra maiúscula';
        if (! preg_match('/\p{Ll}/u', $senha)) $erros[] = 'ter uma letra minúscula';
        if (! preg_match('/\d/', $senha)) $erros[] = 'ter um número';

        return $erros;
    }

    public static function mensagem(array $erros): string
    {
        return 'A senha precisa '.self::juntar($erros).'.';
    }

    private static function juntar(array $itens): string
    {
        return count($itens) > 1 ? implode(', ', array_slice($itens, 0, -1)).' e '.end($itens) : (string) reset($itens);
    }
}

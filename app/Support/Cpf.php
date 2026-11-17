<?php
namespace App\Support;

/** CPF: normalização (só dígitos), validação dos dígitos verificadores e máscara para exibição. */
class Cpf
{
    public static function normalizar(?string $cpf): ?string
    {
        $digitos = preg_replace('/\D/', '', (string) $cpf);

        return $digitos === '' ? null : $digitos;
    }

    public static function valido(?string $cpf): bool
    {
        $c = self::normalizar($cpf);
        if ($c === null || strlen($c) !== 11 || preg_match('/^(\d)\1{10}$/', $c)) return false;
        for ($t = 9; $t < 11; $t++) {
            $soma = 0;
            for ($i = 0; $i < $t; $i++) $soma += (int) $c[$i] * ($t + 1 - $i);
            if ((int) $c[$t] !== ((10 * $soma) % 11) % 10) return false;
        }

        return true;
    }

    /** 12345678909 → 123.456.789-09 */
    public static function formatar(?string $cpf): ?string
    {
        $c = self::normalizar($cpf);

        return $c && strlen($c) === 11 ? substr($c, 0, 3).'.'.substr($c, 3, 3).'.'.substr($c, 6, 3).'-'.substr($c, 9) : $c;
    }

    /** Para auditoria/logs: ***.456.789-** */
    public static function mascarar(?string $cpf): ?string
    {
        $c = self::normalizar($cpf);

        return $c && strlen($c) === 11 ? '***.'.substr($c, 3, 3).'.'.substr($c, 6, 3).'-**' : null;
    }
}

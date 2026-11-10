<?php
namespace App\Support;
class Cpf {
 // Mantém só os dígitos; vazio vira null.
 public static function digits(mixed $value):?string {$d=preg_replace('/\D/','',(string)$value);return $d===''?null:$d;}
 public static function valid(?string $cpf):bool {
  if($cpf===null||!preg_match('/^\d{11}$/',$cpf)||preg_match('/^(\d)\1{10}$/',$cpf))return false;
  for($t=9;$t<11;$t++){$sum=0;for($i=0;$i<$t;$i++)$sum+=(int)$cpf[$i]*($t+1-$i);if((int)$cpf[$t]!==($sum*10%11)%10)return false;}
  return true;
 }
 public static function rule():\Closure {return function(string $attribute,mixed $value,\Closure $fail){if(!self::valid((string)$value))$fail('Informe um CPF válido.');};}
}

<?php
namespace App\Support;
// Dono dos dados na requisição atual: o usuário logado ou, no console (sync/importação), o usuário definido com set().
class Tenant {
 private static ?int $userId=null;
 public static function id():?int {return self::$userId??(auth()->id()===null?null:(int)auth()->id());}
 public static function set(?int $userId):void {self::$userId=$userId;}
 public static function run(?int $userId,callable $callback):mixed {$previous=self::$userId;self::$userId=$userId;try{return $callback();}finally{self::$userId=$previous;}}
}

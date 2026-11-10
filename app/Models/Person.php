<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
class Person extends Model {use HasUuids,SoftDeletes,BelongsToUser;protected $fillable=['name','active','cpf'];protected function casts():array{return ['active'=>'boolean','linked_user_id'=>'integer'];}
 // IDs das pessoas (de outros usuários) cujo compartilhamento este usuário aceitou.
 public static function sharedWith(int $userId){return static::withoutGlobalScope('owner')->where('linked_user_id',$userId)->where('link_status','accepted')->select('id');}
}

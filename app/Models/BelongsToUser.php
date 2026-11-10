<?php
namespace App\Models;
use App\Support\Tenant;
use Illuminate\Database\Eloquent\Builder;
// Isola os registros por usuário: filtra pelo dono atual e preenche user_id na criação.
trait BelongsToUser {
 public static function bootBelongsToUser():void {
  static::addGlobalScope('owner',function(Builder $q){$id=Tenant::id();if($id!==null)$q->where($q->getModel()->getTable().'.user_id',$id);});
  static::creating(function($model){if(!$model->user_id)$model->user_id=Tenant::id();});
 }
}

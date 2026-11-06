<?php
namespace App\Services;
use Illuminate\Support\Facades\DB;
class Audit {
 public static function write(string $action,string $entity,?string $id,?array $before,?array $after,string $origin='app'):void {
  DB::table('audit_logs')->insert(['user_id'=>auth()->id(),'actor_name'=>auth()->user()?->name,'origin'=>$origin,'action'=>$action,'entity'=>$entity,'entity_id'=>$id,'before_data'=>$before===null?null:json_encode($before,JSON_UNESCAPED_UNICODE),'after_data'=>$after===null?null:json_encode($after,JSON_UNESCAPED_UNICODE),'ip_address'=>request()?->ip(),'created_at'=>now()]);
 }
}

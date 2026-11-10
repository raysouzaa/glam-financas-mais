<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 private const OWNED=['people','categories','financial_accounts','credit_cards','transactions'];
 public function up():void {
  Schema::table('users',function(Blueprint $t){$t->string('cpf',11)->nullable()->unique();});
  foreach(self::OWNED as $table)Schema::table($table,function(Blueprint $t){$t->foreignId('user_id')->nullable()->index()->constrained('users');});
  // Os dados existentes passam a pertencer ao primeiro usuário. Sem usuários, ficam sem dono até o primeiro ser criado.
  $first=DB::table('users')->min('id');
  if($first)foreach(self::OWNED as $table)DB::table($table)->update(['user_id'=>$first]);
  Schema::table('people',function(Blueprint $t){
   $t->dropUnique(['name']);$t->unique(['user_id','name']);
   $t->string('cpf',11)->nullable();$t->foreignId('linked_user_id')->nullable()->constrained('users')->nullOnDelete();
   $t->string('link_status',10)->nullable();$t->timestampTz('link_responded_at')->nullable();
   $t->unique(['user_id','cpf']);$t->index(['linked_user_id','link_status']);$t->index('cpf');
  });
  Schema::table('categories',function(Blueprint $t){$t->dropUnique(['name','type']);$t->unique(['user_id','name','type']);});
 }
 public function down():void {
  Schema::table('categories',function(Blueprint $t){$t->dropUnique(['user_id','name','type']);$t->unique(['name','type']);});
  Schema::table('people',function(Blueprint $t){
   $t->dropUnique(['user_id','cpf']);$t->dropIndex(['linked_user_id','link_status']);$t->dropIndex(['cpf']);$t->dropConstrainedForeignId('linked_user_id');
   $t->dropColumn(['cpf','link_status','link_responded_at']);$t->dropUnique(['user_id','name']);$t->unique(['name']);
  });
  foreach(self::OWNED as $table)Schema::table($table,function(Blueprint $t){$t->dropConstrainedForeignId('user_id');});
  Schema::table('users',function(Blueprint $t){$t->dropUnique(['cpf']);$t->dropColumn('cpf');});
 }
};

<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
return new class extends Migration {
 public function up():void {
  Schema::create('database_change_logs',function(Blueprint $t){$t->bigIncrements('id');$t->string('table_name',100);$t->string('operation',10);$t->string('record_id',80)->nullable();$t->jsonb('before_data')->nullable();$t->jsonb('after_data')->nullable();$t->string('database_user',100);$t->timestampTz('changed_at')->useCurrent();$t->index(['table_name','record_id']);});
  DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION financaplus_capture_changes() RETURNS trigger AS $$
BEGIN
 IF TG_OP = 'INSERT' THEN
  INSERT INTO database_change_logs(table_name, operation, record_id, after_data, database_user, changed_at)
  VALUES(TG_TABLE_NAME, TG_OP, NEW.id::text, to_jsonb(NEW), current_user, now());
  RETURN NEW;
 ELSIF TG_OP = 'UPDATE' THEN
  INSERT INTO database_change_logs(table_name, operation, record_id, before_data, after_data, database_user, changed_at)
  VALUES(TG_TABLE_NAME, TG_OP, NEW.id::text, to_jsonb(OLD), to_jsonb(NEW), current_user, now());
  RETURN NEW;
 ELSE
  INSERT INTO database_change_logs(table_name, operation, record_id, before_data, database_user, changed_at)
  VALUES(TG_TABLE_NAME, TG_OP, OLD.id::text, to_jsonb(OLD), current_user, now());
  RETURN OLD;
 END IF;
END;
$$ LANGUAGE plpgsql;
SQL);
  foreach(['people','categories','financial_accounts','credit_cards','transactions','installments'] as $table){DB::statement("CREATE TRIGGER {$table}_audit_trigger AFTER INSERT OR UPDATE OR DELETE ON {$table} FOR EACH ROW EXECUTE FUNCTION financaplus_capture_changes()");}
 }
 public function down():void {foreach(['people','categories','financial_accounts','credit_cards','transactions','installments'] as $table)DB::statement("DROP TRIGGER IF EXISTS {$table}_audit_trigger ON {$table}");DB::statement('DROP FUNCTION IF EXISTS financaplus_capture_changes()');Schema::dropIfExists('database_change_logs');}
};

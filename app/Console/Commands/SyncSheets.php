<?php
namespace App\Console\Commands;
use App\Services\GoogleSheetSync;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
class SyncSheets extends Command {
 protected $signature='finance:sync-sheets';protected $description='Sincroniza a aba do GlamFinanças com o PostgreSQL';
 public function handle():int{
  if(!config('services.google_sheets.spreadsheet_id')){$this->warn('GOOGLE_SHEETS_ID não configurado; sincronização desativada.');return self::SUCCESS;}
  $lock=Cache::lock('financa-sync-lock',120);if(!$lock->get())return self::SUCCESS;
  try{$result=app(GoogleSheetSync::class)->run();DB::table('sync_runs')->insert(['status'=>'success',...$result,'created_at'=>now()]);$this->info(json_encode($result));return self::SUCCESS;}
  catch(\Throwable $e){DB::table('sync_runs')->insert(['status'=>'error','message'=>mb_substr($e->getMessage(),0,2000),'created_at'=>now()]);$this->error($e->getMessage());return self::FAILURE;}
  finally{$lock->release();}
 }
}

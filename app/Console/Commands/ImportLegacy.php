<?php
namespace App\Console\Commands;
use App\Models\{Person,Transaction,User};
use App\Services\{InstallmentService,Audit};
use Carbon\Carbon;
use Google\Client;
use Google\Service\Sheets;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Ramsey\Uuid\Uuid;
class ImportLegacy extends Command {
 protected $signature='finance:import-legacy {--dry-run : Simula sem gravar} {--usuario= : e-mail da conta que recebe os lançamentos (padrão: admin mais antigo)}';
 protected $description='Importa as linhas A3:E da aba Lançamentos, sem alterar a planilha original';
 private function parseDate(mixed $value):Carbon {
  $date=trim((string)$value);
  if(is_numeric($date)){
   $serial=(float)$date;
   if(!is_finite($serial)||$serial<=0||$serial>2958465)throw new \InvalidArgumentException('Data serial inválida');
   return Carbon::create(1899,12,30,0,0,0,'UTC')->addDays((int)floor($serial));
  }
  if(preg_match('/^\d{2}\/\d{2}\/\d{4}$/',$date)){
   $parsed=Carbon::createFromFormat('!d/m/Y',$date,'UTC');
   if(!$parsed||$parsed->format('d/m/Y')!==$date)throw new \InvalidArgumentException('Data inválida');
   return $parsed;
  }
  return Carbon::parse($date,'UTC');
 }
 public function handle():int{
  $id=config('services.google_sheets.spreadsheet_id');$credentials=config('services.google_sheets.credentials');
  if(!$id||!is_readable($credentials)){$this->error('Configure GOOGLE_SHEETS_ID e credenciais.');return self::FAILURE;}
  $dono=User::donoPadrao($this->option('usuario'));if(!$dono){$this->error('Conta de destino não encontrada.');return self::FAILURE;}$uid=(int)$dono->id;
  $c=new Client();$c->setAuthConfig($credentials);$c->addScope(Sheets::SPREADSHEETS_READONLY);$s=new Sheets($c);
  $rows=$s->spreadsheets_values->get($id,"'Lançamentos'!A3:E",['valueRenderOption'=>'UNFORMATTED_VALUE','dateTimeRenderOption'=>'SERIAL_NUMBER'])->getValues()??[];
  $count=0;$skipped=0;
  foreach($rows as $i=>$row){$name=trim((string)($row[0]??''));$description=trim((string)($row[1]??''));$amount=$row[2]??null;$quantity=(int)($row[3]??1);$date=trim((string)($row[4]??''));
   if(!$name||!$description||!is_numeric($amount)||$amount<=0){$skipped++;continue;}
   try{$start=$this->parseDate($date)->startOfMonth()->format('Y-m-d');}catch(\Throwable $e){$skipped++;$this->warn('Data inválida na linha '.($i+3));continue;}
   $uuid=Uuid::uuid5('6ba7b810-9dad-11d1-80b4-00c04fd430c8',$id.':legacy:'.($i+3))->toString();
   if(Transaction::withTrashed()->whereKey($uuid)->exists()){$skipped++;continue;}
   if(!$this->option('dry-run'))DB::transaction(function()use($name,$description,$amount,$quantity,$start,$uuid,$uid){$person=Person::firstOrCreate(['user_id'=>$uid,'name'=>$name]);$t=new Transaction();$t->id=$uuid;$t->fill(['user_id'=>$uid,'person_id'=>$person->id,'description'=>$description,'amount_cents'=>(int)round((float)$amount*100),'installments_count'=>max(1,$quantity),'start_month'=>$start,'type'=>'expense','payment_method'=>'other','origin'=>'legacy']);$t->save();app(InstallmentService::class)->rebuild($t);Audit::write('import','transactions',$uuid,null,$t->toArray(),'import');});
   $count++;
  }
  $this->info(($this->option('dry-run')?'Simulação: ':'Importação: ').$count.' lançamentos; '.$skipped.' ignorados.');return self::SUCCESS;
 }
}

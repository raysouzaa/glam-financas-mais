<?php
namespace App\Services;
use App\Models\{Transaction,Person,Category,CreditCard,FinancialAccount,User};
use Google\Client;
use Google\Service\Sheets;
use Google\Service\Sheets\ValueRange;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
class GoogleSheetSync {
 private Sheets $service;private string $spreadsheet;private string $tab;
 /** Conta dona dos lançamentos sincronizados (GOOGLE_SHEETS_OWNER_EMAIL; padrão: admin mais antigo). A planilha nunca vê dados de outras contas. */
 private int $owner;
 public function __construct(){
  $owner=User::donoPadrao();if(!$owner)throw new \RuntimeException('Nenhuma conta para ser dona da planilha: defina GOOGLE_SHEETS_OWNER_EMAIL ou crie um administrador.');$this->owner=(int)$owner->id;
  $this->spreadsheet=(string)config('services.google_sheets.spreadsheet_id');$this->tab=(string)config('services.google_sheets.tab');
  if(!$this->spreadsheet)throw new \RuntimeException('Configure GOOGLE_SHEETS_ID.');
  $file=config('services.google_sheets.credentials');if(!is_readable($file))throw new \RuntimeException('Arquivo de credenciais do Google não encontrado.');
  $client=new Client();$client->setAuthConfig($file);$client->addScope(Sheets::SPREADSHEETS);$this->service=new Sheets($client);
 }
 private const HEADER=['ID','Pessoa','Descrição','Valor (centavos)','Parcelas','Mês início','Tipo','Categoria','Pagamento','Cartão','Conta','Observações','Excluído'];
 private function range(string $a):string {return "'".str_replace("'","''",$this->tab)."'!".$a;}
 private function canonical(Transaction $t):array{return [$t->id,$t->person?->name??'',(string)$t->description,(string)$t->amount_cents,(string)$t->installments_count,substr((string)$t->start_month,0,10),$t->type,$t->category?->name??'',$t->payment_method,$t->creditCard?->name??'',$t->financialAccount?->name??'',(string)($t->notes??''),$t->trashed()?'SIM':'NÃO'];}
 private function norm(array $row):array{$row=array_pad(array_slice($row,0,13),13,'');return array_map(fn($v)=>trim((string)$v),$row);}
 private function hash(array $row):string{return hash('sha256',json_encode($this->norm($row),JSON_UNESCAPED_UNICODE));}
 private function writeRow(int $n,array $row):void{$this->service->spreadsheets_values->update($this->spreadsheet,$this->range('A'.$n.':M'.$n),new ValueRange(['values'=>[$row]]),['valueInputOption'=>'RAW']);}
 private function idFor(string $model,string $name,?string $type=null):?string {
  if(trim($name)==='')return null;
  $class=match($model){'people'=>Person::class,'categories'=>Category::class,'credit_cards'=>CreditCard::class,'financial_accounts'=>FinancialAccount::class};
  $data=['user_id'=>$this->owner,'name'=>trim($name)];if($model==='categories')$data['type']=$type;
  $found=$class::withTrashed()->firstOrCreate($data);if($found->trashed())$found->restore();return $found->id;
 }
 private function remoteData(array $r):array{
  $r=$this->norm($r);if(!$r[2]||!is_numeric($r[3])||!is_numeric($r[4])||!preg_match('/^\d{4}-\d{2}-\d{2}$/',$r[5]))throw new \InvalidArgumentException('Linha da planilha inválida.');
  $type=in_array($r[6],['expense','income'])?$r[6]:'expense';$method=in_array($r[8],['credit_card','debit_card','pix','cash','transfer','boleto','other'])?$r[8]:'other';
  return ['person_id'=>$this->idFor('people',$r[1]),'description'=>$r[2],'amount_cents'=>(int)$r[3],'installments_count'=>(int)$r[4],'start_month'=>$r[5],'type'=>$type,'category_id'=>$this->idFor('categories',$r[7],$type),'payment_method'=>$method,'credit_card_id'=>$this->idFor('credit_cards',$r[9]),'financial_account_id'=>$this->idFor('financial_accounts',$r[10]),'notes'=>$r[11], 'delete_requested'=>strtoupper($r[12])==='SIM'];
 }
 private function conflict(?string $id,array $local,array $remote,string $reason):void{if(DB::table('sync_conflicts')->where('transaction_id',$id)->where('reason',$reason)->whereNull('resolved_at')->exists())return;DB::table('sync_conflicts')->insert(['transaction_id'=>$id,'local_data'=>json_encode($local,JSON_UNESCAPED_UNICODE),'remote_data'=>json_encode($remote,JSON_UNESCAPED_UNICODE),'reason'=>$reason,'created_at'=>now()]);}
 public function run():array {
  // Exclusão de linhas da planilha não significa apagar dados; edição deve ocorrer na coluna Excluído.
  $sheet=$this->service->spreadsheets->get($this->spreadsheet);$existing=collect($sheet->getSheets())->contains(fn($s)=>$s->getProperties()->getTitle()===$this->tab);
  if(!$existing)$this->service->spreadsheets->batchUpdate($this->spreadsheet,new \Google\Service\Sheets\BatchUpdateSpreadsheetRequest(['requests'=>[['addSheet'=>['properties'=>['title'=>$this->tab]]]]]));
  $this->writeRow(1,self::HEADER);
  $values=$this->service->spreadsheets_values->get($this->spreadsheet,$this->range('A2:M'))->getValues()??[];
  $seen=[];$pulled=0;$pushed=0;$conflicts=0;
  foreach($values as $index=>$raw){$row=$this->norm($raw);$rowNumber=$index+2;if(!array_filter($row))continue;
   $id=$row[0];if(!$id){$id=(string)Str::uuid();$row[0]=$id;$this->writeRow($rowNumber,$row);}
   if(!Str::isUuid($id)){$this->conflict(null,[],$row,'ID inválido na planilha');$conflicts++;continue;}
   if(isset($seen[$id])){$this->conflict($id,[],$row,'ID duplicado na planilha');$conflicts++;continue;}$seen[$id]=true;
   $remoteHash=$this->hash($row);$local=Transaction::withTrashed()->with(['person','category','creditCard','financialAccount'])->find($id);$state=DB::table('sheet_sync_states')->where('transaction_id',$id)->first();
   if($local&&(int)$local->user_id!==$this->owner){$this->conflict($id,[],$row,'ID pertence a um lançamento de outra conta');$conflicts++;continue;}
   if(!$local){try{DB::transaction(function()use($id,$row,$remoteHash,$rowNumber,&$pulled){$data=$this->remoteData($row);$delete=$data['delete_requested'];unset($data['delete_requested']);if($data['amount_cents']<=0||$data['installments_count']<1||$data['installments_count']>120)throw new \InvalidArgumentException('Valor ou parcelas inválidos');$t=new Transaction();$t->id=$id;$t->fill($data+['origin'=>'sheets','user_id'=>$this->owner]);$t->save();app(InstallmentService::class)->rebuild($t);if($delete)$t->delete();Audit::write('import','transactions',$id,null,$t->toArray(),'sheets');DB::table('sheet_sync_states')->insert(['transaction_id'=>$id,'last_hash'=>$remoteHash,'sheet_row'=>$rowNumber,'synced_at'=>now()]);$pulled++;});}catch(\Throwable $e){$this->conflict($id,[],$row,$e->getMessage());$conflicts++;}continue;}
   $localRow=$this->canonical($local);$localHash=$this->hash($localRow);$base=$state?->last_hash;
   if(!$base){if($localHash===$remoteHash){DB::table('sheet_sync_states')->updateOrInsert(['transaction_id'=>$id],['last_hash'=>$remoteHash,'sheet_row'=>$rowNumber,'synced_at'=>now()]);}else{$this->conflict($id,$localRow,$row,'Registro existente sem base comum');$conflicts++;}continue;}
   if($remoteHash!==$base && $localHash!==$base){$this->conflict($id,$localRow,$row,'Alterado nos dois lados');$conflicts++;continue;}
   if($remoteHash!==$base && $localHash===$base){try{DB::transaction(function()use($id,$row,$remoteHash,$rowNumber,&$pulled){$t=Transaction::withTrashed()->findOrFail($id);if($t->installments()->where('paid',true)->exists())throw new \DomainException('Há parcelas pagas');$before=$t->toArray();$data=$this->remoteData($row);$delete=$data['delete_requested'];unset($data['delete_requested']);if($data['amount_cents']<=0||$data['installments_count']<1||$data['installments_count']>120)throw new \InvalidArgumentException('Valor ou parcelas inválidos');$t->update($data);app(InstallmentService::class)->rebuild($t);if($delete)$t->delete();else if($t->trashed())$t->restore();Audit::write('external_update','transactions',$id,$before,$t->toArray(),'sheets');DB::table('sheet_sync_states')->updateOrInsert(['transaction_id'=>$id],['last_hash'=>$remoteHash,'sheet_row'=>$rowNumber,'synced_at'=>now()]);$pulled++;});}catch(\Throwable $e){$this->conflict($id,$localRow,$row,$e->getMessage());$conflicts++;}continue;}
   if($localHash!==$base && $remoteHash===$base){$this->writeRow($rowNumber,$localRow);DB::table('sheet_sync_states')->updateOrInsert(['transaction_id'=>$id],['last_hash'=>$localHash,'sheet_row'=>$rowNumber,'synced_at'=>now()]);$pushed++;}
  }
  // Novos lançamentos locais: append na aba dedicada.
  $new=Transaction::withTrashed()->where('user_id',$this->owner)->with(['person','category','creditCard','financialAccount'])->whereNotIn('id',DB::table('sheet_sync_states')->select('transaction_id'))->get();
  foreach($new as $t){if(isset($seen[$t->id]))continue;$row=$this->canonical($t);$this->service->spreadsheets_values->append($this->spreadsheet,$this->range('A:M'),new ValueRange(['values'=>[$row]]),['valueInputOption'=>'RAW','insertDataOption'=>'INSERT_ROWS']);DB::table('sheet_sync_states')->insert(['transaction_id'=>$t->id,'last_hash'=>$this->hash($row),'synced_at'=>now()]);$pushed++;}
  // Registros que já foram sincronizados, mas sumiram da planilha: não restaurar nem excluir automaticamente.
  foreach(DB::table('sheet_sync_states')->get() as $state){if(!isset($seen[$state->transaction_id])){$this->conflict($state->transaction_id,[],[],'Linha removida da planilha; exige reconciliação');$conflicts++;}}
  return compact('pulled','pushed','conflicts');
 }
}

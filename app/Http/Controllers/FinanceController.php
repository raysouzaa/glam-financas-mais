<?php
namespace App\Http\Controllers;
use App\Models\{Person,Category,CreditCard,FinancialAccount,Transaction,Installment};
use App\Services\{Accounts,Audit,InstallmentService};
use App\Support\Cpf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
class FinanceController {
 private function references():array {return ['people'=>Person::orderBy('name')->get(),'categories'=>Category::orderBy('name')->get(),'accounts'=>FinancialAccount::orderBy('name')->get(),'cards'=>CreditCard::orderBy('name')->get()];}
 public function dashboard(Request $r){
  $month=$r->query('month',now()->format('Y-m'));if(!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/',$month))$month=now()->format('Y-m');
  // Só entram os lançamentos do usuário e os compartilhados com ele (pessoa vinculada pelo CPF, convite aceito).
  $uid=(int)auth()->id();$shared=Person::sharedWith($uid)->pluck('id')->all();$visible=fn($w)=>$w->where('t.user_id',$uid)->orWhereIn('t.person_id',$shared);
  $totals=DB::table('installments as i')->join('transactions as t','i.transaction_id','=','t.id')->whereNull('t.deleted_at')->where($visible)->where('i.due_month',$month.'-01')->selectRaw("COALESCE(SUM(CASE WHEN t.type='expense' THEN i.amount_cents ELSE 0 END),0) as expenses,COALESCE(SUM(CASE WHEN t.type='income' THEN i.amount_cents ELSE 0 END),0) as incomes")->first();
  $byPerson=DB::table('installments as i')->join('transactions as t','i.transaction_id','=','t.id')->join('people as p','p.id','=','t.person_id')->whereNull('t.deleted_at')->where($visible)->where('t.type','expense')->where('i.due_month',$month.'-01')->selectRaw('p.name, SUM(i.amount_cents) as total')->groupBy('p.id','p.name')->orderByDesc('total')->get();
  $byCategory=DB::table('installments as i')->join('transactions as t','i.transaction_id','=','t.id')->leftJoin('categories as c','c.id','=','t.category_id')->whereNull('t.deleted_at')->where($visible)->where('t.type','expense')->where('i.due_month',$month.'-01')->selectRaw("COALESCE(c.name,'Sem categoria') as name,SUM(i.amount_cents) as total")->groupBy('c.id','c.name')->orderByDesc('total')->get();
  $trend=[];for($k=-2;$k<=5;$k++){$ym=\Carbon\CarbonImmutable::parse($month.'-01')->addMonths($k)->format('Y-m');$value=DB::table('installments as i')->join('transactions as t','i.transaction_id','=','t.id')->whereNull('t.deleted_at')->where($visible)->where('t.type','expense')->where('i.due_month',$ym.'-01')->sum('i.amount_cents');$trend[]=['month'=>$ym,'total'=>(int)$value];}
  $recent=Transaction::visibleTo($uid)->with(['person','category','creditCard','owner:id,name'])->latest()->limit(12)->get();
  return Inertia::render('Dashboard',array_merge($this->references(),compact('month','totals','byPerson','byCategory','trend','recent')));
 }
 public function manage(Request $request){$kind=$request->query('kind','transactions');abort_unless(in_array($kind,['transactions','people','categories','accounts','cards']),404);$rows=$kind==='transactions'?Transaction::visibleTo((int)auth()->id())->with(['person','category','creditCard','financialAccount','installments','owner:id,name'])->latest()->limit(300)->get():match($kind){'people'=>Person::orderBy('name')->get(),'categories'=>Category::orderBy('name')->get(),'accounts'=>FinancialAccount::orderBy('name')->get(),'cards'=>CreditCard::orderBy('name')->get()};return Inertia::render('Manage',array_merge($this->references(),['kind'=>$kind,'rows'=>$rows]));}
 private function transactionData(Request $r):array {
  $own=fn(string $table)=>Rule::exists($table,'id')->whereNull('deleted_at')->where('user_id',auth()->id());
  $data=$r->validate(['person_id'=>['required',$own('people')],'category_id'=>['nullable',$own('categories')],'credit_card_id'=>['nullable',$own('credit_cards')],'financial_account_id'=>['nullable',$own('financial_accounts')],'type'=>'required|in:expense,income','payment_method'=>'required|in:credit_card,debit_card,pix,cash,transfer,boleto,other','description'=>'required|string|max:255','amount'=>'required|numeric|decimal:0,2|gt:0|max:999999999','installments_count'=>'required|integer|min:1|max:120','start_month'=>'required|date_format:Y-m-d','notes'=>'nullable|string|max:3000']);
  $amount=round((float)$data['amount']*100);$data['amount_cents']=(int)$amount;unset($data['amount']);if($data['amount_cents']<=0)throw \Illuminate\Validation\ValidationException::withMessages(['amount'=>'Informe um valor maior que zero.']);
  if($data['type']==='income'&&$data['installments_count']>1)throw \Illuminate\Validation\ValidationException::withMessages(['installments_count'=>'Receitas não podem ser parceladas nesta versão.']);
  if($data['payment_method']==='credit_card'&&!($data['credit_card_id']??null))throw \Illuminate\Validation\ValidationException::withMessages(['credit_card_id'=>'Selecione um cartão de crédito.']);
  $data['credit_card_id']=$data['payment_method']==='credit_card'?($data['credit_card_id']??null):null;
  $data['start_month']=substr($data['start_month'],0,7).'-01';return $data;
 }
 public function storeTransaction(Request $r,InstallmentService $service){$data=$this->transactionData($r);DB::transaction(function()use($data,$service){$row=Transaction::create($data);$service->rebuild($row);Audit::write('create','transactions',$row->id,null,$row->toArray());});return back()->with('success','Lançamento cadastrado.');}
 public function updateTransaction(Request $r,Transaction $transaction,InstallmentService $service){$data=$this->transactionData($r);if($transaction->installments()->where('paid',true)->exists())return back()->withErrors(['amount'=>'Não é possível alterar um lançamento com parcelas pagas.']);DB::transaction(function()use($transaction,$data,$service){$old=$transaction->toArray();$transaction->update($data);$service->rebuild($transaction);Audit::write('update','transactions',$transaction->id,$old,$transaction->fresh()->toArray());});return back()->with('success','Lançamento alterado.');}
 public function destroyTransaction(Transaction $transaction){if($transaction->installments()->where('paid',true)->exists())return back()->withErrors(['delete'=>'Não é possível excluir um lançamento com parcelas pagas.']);DB::transaction(function()use($transaction){$old=$transaction->toArray();$transaction->delete();Audit::write('delete','transactions',$transaction->id,$old,null);});return back()->with('success','Lançamento excluído.');}
 public function paid(Request $r,Installment $installment){abort_unless(Transaction::whereKey($installment->transaction_id)->exists(),404);$r->validate(['paid'=>'required|boolean']);DB::transaction(function()use($r,$installment){$before=$installment->toArray();$installment->update(['paid'=>$r->boolean('paid'),'paid_at'=>$r->boolean('paid')?now():null]);Audit::write('payment','installments',(string)$installment->id,$before,$installment->fresh()->toArray());});return back()->with('success','Parcela atualizada.');}
 private function model(string $kind){return match($kind){'people'=>Person::class,'categories'=>Category::class,'accounts'=>FinancialAccount::class,'cards'=>CreditCard::class,default=>abort(404)};}
 private function personFields(Request $r,?string $id):array{
  $r->merge(['cpf'=>Cpf::digits($r->input('cpf'))]);$mine=fn(string $column)=>Rule::unique('people',$column)->where('user_id',auth()->id())->ignore($id);
  return $r->validate(['name'=>['required','string','max:120',$mine('name')],'active'=>'boolean','cpf'=>['nullable',Cpf::rule(),$mine('cpf')]],['name.unique'=>'Já existe uma pessoa com este nome.','cpf.unique'=>'Já existe uma pessoa com este CPF.']);
 }
 private function fields(Request $r,string $kind,?string $id=null):array{return match($kind){
  'people'=>$this->personFields($r,$id),
  'categories'=>$r->validate(['name'=>'required|string|max:100','type'=>'required|in:expense,income','color'=>'nullable|regex:/^#[0-9a-fA-F]{6}$/']),
  'accounts'=>$r->validate(['name'=>'required|string|max:120','type'=>'required|in:checking,savings,cash,wallet','opening_balance_cents'=>'nullable|integer']),
  'cards'=>$r->validate(['name'=>'required|string|max:120','last_four'=>'nullable|digits:4','closing_day'=>'nullable|integer|between:1,28','due_day'=>'nullable|integer|between:1,28','limit_cents'=>'nullable|integer|min:0']),
  default=>abort(404)};}
 private const INVITED=' Encontramos um usuário com este CPF e enviamos um convite para ele acompanhar os lançamentos desta pessoa.';
 public function storeReference(Request $r,string $kind){$class=$this->model($kind);$data=$this->fields($r,$kind);$invited=false;DB::transaction(function()use($class,$data,$kind,&$invited){$row=new $class($data);if($row instanceof Person)$invited=Accounts::matchPerson($row);$row->save();Audit::write('create',$kind,$row->id,null,$row->toArray());});return back()->with('success','Cadastro realizado.'.($invited?self::INVITED:''));}
 public function updateReference(Request $r,string $kind,string $id){$class=$this->model($kind);$row=$class::findOrFail($id);$data=$this->fields($r,$kind,$id);$invited=false;DB::transaction(function()use($row,$data,$kind,&$invited){$old=$row->toArray();$row->fill($data);if($row instanceof Person)$invited=Accounts::matchPerson($row);$row->save();Audit::write('update',$kind,$row->id,$old,$row->fresh()->toArray());});return back()->with('success','Cadastro atualizado.'.($invited?self::INVITED:''));}
 // O dono do CPF aceita, recusa ou deixa de acompanhar os lançamentos de uma pessoa cadastrada por outro usuário.
 public function respondInvite(Request $r,string $person){
  $r->validate(['accept'=>'required|boolean']);$row=Person::withoutGlobalScope('owner')->where('linked_user_id',auth()->id())->whereIn('link_status',['pending','accepted'])->findOrFail($person);
  DB::transaction(function()use($r,$row){$before=$row->link_status;$row->forceFill(['link_status'=>$r->boolean('accept')?'accepted':'declined','link_responded_at'=>now()])->save();Audit::write($r->boolean('accept')?'share_accept':'share_decline','people',$row->id,['link_status'=>$before],['link_status'=>$row->link_status]);});
  return back()->with('success',$r->boolean('accept')?'Compartilhamento aceito: os lançamentos já aparecem no seu painel.':'Compartilhamento recusado.');
 }
 public function destroyReference(string $kind,string $id){$class=$this->model($kind);$row=$class::findOrFail($id);try{DB::transaction(function()use($row,$kind){$old=$row->toArray();$row->delete();Audit::write('delete',$kind,$row->id,$old,null);});}catch(\Illuminate\Database\QueryException $e){return back()->withErrors(['delete'=>'Este cadastro está em uso e não pode ser excluído.']);}return back()->with('success','Cadastro excluído.');}
 public function audit(){
  // Cada usuário vê só a própria trilha; eventos da planilha (sem usuário) ficam com o dono da planilha.
  $uid=(int)auth()->id();$sheets=Accounts::sheetOwner()?->id===$uid;
  $logs=DB::table('audit_logs')->where(function($w)use($uid,$sheets){$w->where('user_id',$uid);if($sheets)$w->orWhere(fn($o)=>$o->whereNull('user_id')->whereIn('origin',['sheets','import']));})->orderByDesc('id')->limit(200)->get();
  $dbChanges=DB::table('database_change_logs')->whereRaw("(coalesce(after_data,before_data)->>'user_id'=? or (table_name='installments' and coalesce(after_data,before_data)->>'transaction_id' in (select id::text from transactions where user_id=?)))",[(string)$uid,$uid])->orderByDesc('id')->limit(100)->get();
  return Inertia::render('Audit',['logs'=>$logs,'syncRuns'=>$sheets?DB::table('sync_runs')->orderByDesc('id')->limit(15)->get():[],'conflicts'=>$sheets?DB::table('sync_conflicts')->whereNull('resolved_at')->orderByDesc('id')->limit(30)->get():[],'dbChanges'=>$dbChanges]);
 }
}

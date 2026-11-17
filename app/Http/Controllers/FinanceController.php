<?php
namespace App\Http\Controllers;
use App\Models\{Person,Category,CreditCard,FinancialAccount,Transaction,Installment};
use App\Services\{Audit,InstallmentService};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Support\Cpf;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
class FinanceController {
 /**
  * Lançamentos que o usuário enxerga: os que ele mesmo cadastrou e os que outras contas lançaram
  * em uma Pessoa com o CPF dele, desde que ele tenha aceitado o vínculo com aquele dono (User::vinculos()).
  * Os de outras contas são somente leitura: toda escrita confere o dono com meu().
  */
 private function visiveis($q,Request $r,string $t='t'){$u=$r->user();$pessoas=$u->pessoasVinculadas();return $q->where(function($w)use($u,$pessoas,$t){$w->where("$t.user_id",$u->id);if($pessoas)$w->orWhereIn("$t.person_id",$pessoas);});}
 /** Parcelas dos lançamentos visíveis (base de todos os números do dashboard). */
 private function parcelas(Request $r){return $this->visiveis(DB::table('installments as i')->join('transactions as t','i.transaction_id','=','t.id')->whereNull('t.deleted_at'),$r);}
 private function uid(Request $r):int{return (int)$r->user()->id;}
 private function meu(Request $r,Transaction $t):void{abort_unless((int)$t->user_id===$this->uid($r),404);}
 private function references(Request $r):array {$uid=$this->uid($r);return ['people'=>Person::where('user_id',$uid)->orderBy('name')->get(),'categories'=>Category::where('user_id',$uid)->orderBy('name')->get(),'accounts'=>FinancialAccount::where('user_id',$uid)->orderBy('name')->get(),'cards'=>CreditCard::where('user_id',$uid)->orderBy('name')->get()];}
 /** Nos lançamentos de outra conta, cartão e conta bancária do dono não são enviados ao navegador. */
 private function ocultarDadosDoDono($rows,Request $r){$uid=$this->uid($r);foreach($rows as $t){if((int)$t->user_id!==$uid){$t->setRelation('creditCard',null);$t->setRelation('financialAccount',null);$t->makeHidden(['credit_card_id','financial_account_id','notes']);}}return $rows;}
 public function dashboard(Request $r){
  $month=$r->query('month',now()->format('Y-m'));if(!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/',$month))$month=now()->format('Y-m');
  $totals=$this->parcelas($r)->where('i.due_month',$month.'-01')->selectRaw("COALESCE(SUM(CASE WHEN t.type='expense' THEN i.amount_cents ELSE 0 END),0) as expenses,COALESCE(SUM(CASE WHEN t.type='income' THEN i.amount_cents ELSE 0 END),0) as incomes")->first();
  // Lançamento sem pessoa é do próprio dono da conta ("Eu").
  $byPerson=$this->parcelas($r)->leftJoin('people as p','p.id','=','t.person_id')->where('t.type','expense')->where('i.due_month',$month.'-01')->selectRaw("COALESCE(p.name,'Eu') as name, SUM(i.amount_cents) as total")->groupByRaw("COALESCE(p.name,'Eu')")->orderByDesc('total')->get();
  $byCategory=$this->parcelas($r)->leftJoin('categories as c','c.id','=','t.category_id')->where('t.type','expense')->where('i.due_month',$month.'-01')->selectRaw("COALESCE(c.name,'Sem categoria') as name,SUM(i.amount_cents) as total")->groupByRaw("COALESCE(c.name,'Sem categoria')")->orderByDesc('total')->get();
  $trend=[];for($k=-2;$k<=5;$k++){$ym=\Carbon\CarbonImmutable::parse($month.'-01')->addMonths($k)->format('Y-m');$value=$this->parcelas($r)->where('t.type','expense')->where('i.due_month',$ym.'-01')->sum('i.amount_cents');$trend[]=['month'=>$ym,'total'=>(int)$value];}
  $recent=$this->ocultarDadosDoDono($this->visiveis(Transaction::with(['person','category','creditCard','owner:id,name']),$r,'transactions')->latest()->limit(12)->get(),$r);
  return Inertia::render('Dashboard',array_merge($this->references($r),compact('month','totals','byPerson','byCategory','trend','recent')));
 }
 public function manage(Request $request){
  $kind=$request->query('kind','transactions');abort_unless(in_array($kind,['transactions','people','categories','accounts','cards']),404);$uid=$this->uid($request);
  $rows=match($kind){
   'transactions'=>$this->ocultarDadosDoDono($this->visiveis(Transaction::with(['person','category','creditCard','financialAccount','installments'=>fn($q)=>$q->orderBy('number'),'owner:id,name']),$request,'transactions')->latest()->limit(300)->get(),$request),
   'people'=>$this->comVinculo(Person::where('user_id',$uid)->orderBy('name')->get(),$uid),
   'categories'=>Category::where('user_id',$uid)->orderBy('name')->get(),
   'accounts'=>FinancialAccount::where('user_id',$uid)->orderBy('name')->get(),
   'cards'=>CreditCard::where('user_id',$uid)->orderBy('name')->get()};
  return Inertia::render('Manage',array_merge($this->references($request),['kind'=>$kind,'rows'=>$rows]));
 }
 /**
  * Marca as pessoas com CPF cujo dono do CPF já aceitou o vínculo (link_accepted). Não diferencia
  * "sem conta", "pendente" e "removido", para não revelar se um CPF tem conta no sistema.
  */
 private function comVinculo($people,int $uid){$aceitos=DB::table('account_links as l')->join('users as u','u.id','=','l.user_id')->where('l.owner_user_id',$uid)->where('l.status','accepted')->pluck('u.cpf')->filter()->flip();foreach($people as $p)$p->setAttribute('link_accepted',$p->cpf?isset($aceitos[$p->cpf]):null);return $people;}
 private function transactionData(Request $r):array {
  // Pessoa é opcional (sem pessoa = lançamento do próprio usuário); só valem cadastros da própria conta.
  $uid=$this->uid($r);$meu=fn(string $tabela)=>Rule::exists($tabela,'id')->where('user_id',$uid)->whereNull('deleted_at');
  $data=$r->validate(['person_id'=>['nullable',$meu('people')],'category_id'=>['nullable',$meu('categories')],'credit_card_id'=>['nullable',$meu('credit_cards')],'financial_account_id'=>['nullable',$meu('financial_accounts')],'type'=>'required|in:expense,income','payment_method'=>'required|in:credit_card,debit_card,pix,cash,transfer,boleto,other','description'=>'required|string|max:255','amount'=>'required|numeric|decimal:0,2|gt:0|max:999999999','installments_count'=>'required|integer|min:1|max:120','start_month'=>'required|date_format:Y-m-d','notes'=>'nullable|string|max:3000']);
  $amount=round((float)$data['amount']*100);$data['amount_cents']=(int)$amount;unset($data['amount']);if($data['amount_cents']<=0)throw \Illuminate\Validation\ValidationException::withMessages(['amount'=>'Informe um valor maior que zero.']);
  if($data['type']==='income'&&$data['installments_count']>1)throw \Illuminate\Validation\ValidationException::withMessages(['installments_count'=>'Receitas não podem ser parceladas nesta versão.']);
  // Receita: tipo de receita obrigatório (Salário, Pensão, PJ...) e só formas de recebimento (sem cartão).
  if($data['type']==='income'&&!($data['category_id']??null))throw \Illuminate\Validation\ValidationException::withMessages(['category_id'=>'Selecione o tipo de receita.']);
  if($data['type']==='income'&&in_array($data['payment_method'],['credit_card','debit_card'],true))throw \Illuminate\Validation\ValidationException::withMessages(['payment_method'=>'Selecione como a receita foi recebida (Pix, transferência, dinheiro, boleto ou outro).']);
  if(($data['category_id']??null)&&Category::whereKey($data['category_id'])->value('type')!==$data['type'])throw \Illuminate\Validation\ValidationException::withMessages(['category_id'=>$data['type']==='income'?'Selecione um tipo de receita.':'Selecione uma categoria de despesa.']);
  if($data['payment_method']==='credit_card'&&!($data['credit_card_id']??null))throw \Illuminate\Validation\ValidationException::withMessages(['credit_card_id'=>'Selecione um cartão de crédito.']);
  $data['credit_card_id']=$data['payment_method']==='credit_card'?($data['credit_card_id']??null):null;
  $data['person_id']=$data['person_id']??null;$data['start_month']=substr($data['start_month'],0,7).'-01';return $data;
 }
 public function storeTransaction(Request $r,InstallmentService $service){$data=$this->transactionData($r)+['user_id'=>$this->uid($r)];DB::transaction(function()use($data,$service){$row=Transaction::create($data);$service->rebuild($row);Audit::write('create','transactions',$row->id,null,$row->toArray());});return back()->with('success','Lançamento cadastrado.');}
 public function updateTransaction(Request $r,Transaction $transaction,InstallmentService $service){$this->meu($r,$transaction);$data=$this->transactionData($r);if($transaction->installments()->where('paid',true)->exists())return back()->withErrors(['amount'=>'Não é possível alterar um lançamento com parcelas pagas.']);DB::transaction(function()use($transaction,$data,$service){$old=$transaction->toArray();$transaction->update($data);$service->rebuild($transaction);Audit::write('update','transactions',$transaction->id,$old,$transaction->fresh()->toArray());});return back()->with('success','Lançamento alterado.');}
 public function destroyTransaction(Request $r,Transaction $transaction){$this->meu($r,$transaction);if($transaction->installments()->where('paid',true)->exists())return back()->withErrors(['delete'=>'Não é possível excluir um lançamento com parcelas pagas.']);DB::transaction(function()use($transaction){$old=$transaction->toArray();$transaction->delete();Audit::write('delete','transactions',$transaction->id,$old,null);});return back()->with('success','Lançamento excluído.');}
 public function paid(Request $r,Installment $installment){abort_unless((int)Transaction::withTrashed()->whereKey($installment->transaction_id)->value('user_id')===$this->uid($r),404);$r->validate(['paid'=>'required|boolean']);DB::transaction(function()use($r,$installment){$before=$installment->toArray();$installment->update(['paid'=>$r->boolean('paid'),'paid_at'=>$r->boolean('paid')?now():null]);Audit::write('payment','installments',(string)$installment->id,$before,$installment->fresh()->toArray());});return back()->with('success','Parcela atualizada.');}
 private function model(string $kind){return match($kind){'people'=>Person::class,'categories'=>Category::class,'accounts'=>FinancialAccount::class,'cards'=>CreditCard::class,default=>abort(404)};}
 private function fields(Request $r,string $kind,?string $id=null):array{$uid=$this->uid($r);if($kind==='people')$r->merge(['cpf'=>Cpf::normalizar($r->input('cpf'))]);return match($kind){
  'people'=>$r->validate(['name'=>['required','string','max:120',Rule::unique('people','name')->where('user_id',$uid)->ignore($id)],'cpf'=>['nullable',Rule::unique('people','cpf')->where('user_id',$uid)->ignore($id),function(string $a,mixed $v,\Closure $fail){if(!Cpf::valido((string)$v))$fail('Informe um CPF válido.');}],'active'=>'boolean'],['cpf.unique'=>'Você já cadastrou uma pessoa com este CPF.','name.unique'=>'Você já cadastrou uma pessoa com este nome.']),
  'categories'=>$r->validate(['name'=>['required','string','max:100',Rule::unique('categories','name')->where('user_id',$uid)->where('type',$r->input('type'))->ignore($id)],'type'=>'required|in:expense,income','color'=>'nullable|regex:/^#[0-9a-fA-F]{6}$/'],['name.unique'=>'Você já tem uma categoria com este nome.']),
  'accounts'=>$r->validate(['name'=>'required|string|max:120','type'=>'required|in:checking,savings,cash,wallet','opening_balance_cents'=>'nullable|integer']),
  'cards'=>$r->validate(['name'=>'required|string|max:120','last_four'=>'nullable|digits:4','closing_day'=>'nullable|integer|between:1,28','due_day'=>'nullable|integer|between:1,28','limit_cents'=>'nullable|integer|min:0']),
  default=>abort(404)};}
 public function storeReference(Request $r,string $kind){$class=$this->model($kind);$data=$this->fields($r,$kind)+['user_id'=>$this->uid($r)];DB::transaction(function()use($class,$data,$kind){$row=$class::create($data);Audit::write('create',$kind,$row->id,null,$row->toArray());});return back()->with('success','Cadastro realizado.');}
 public function updateReference(Request $r,string $kind,string $id){$class=$this->model($kind);$row=$class::where('user_id',$this->uid($r))->findOrFail($id);$data=$this->fields($r,$kind,$id);DB::transaction(function()use($row,$data,$kind){$old=$row->toArray();$row->update($data);Audit::write('update',$kind,$row->id,$old,$row->fresh()->toArray());});return back()->with('success','Cadastro atualizado.');}
 public function destroyReference(Request $r,string $kind,string $id){$class=$this->model($kind);$row=$class::where('user_id',$this->uid($r))->findOrFail($id);try{DB::transaction(function()use($row,$kind){$old=$row->toArray();$row->delete();Audit::write('delete',$kind,$row->id,$old,null);});}catch(\Illuminate\Database\QueryException $e){return back()->withErrors(['delete'=>'Este cadastro está em uso e não pode ser excluído.']);}return back()->with('success','Cadastro excluído.');}
 public function audit(){return Inertia::render('Audit',['logs'=>DB::table('audit_logs')->orderByDesc('id')->limit(200)->get(),'syncRuns'=>DB::table('sync_runs')->orderByDesc('id')->limit(15)->get(),'conflicts'=>DB::table('sync_conflicts')->whereNull('resolved_at')->orderByDesc('id')->limit(30)->get(),'dbChanges'=>DB::table('database_change_logs')->orderByDesc('id')->limit(100)->get()]);}
}

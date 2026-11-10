<?php
namespace App\Services;
use App\Models\{Category,Person,User};
use App\Support\Tenant;
use Illuminate\Support\Facades\DB;
class Accounts {
 public const OWNED=['people','categories','financial_accounts','credit_cards','transactions'];
 // Cria o usuário; o primeiro herda os dados sem dono (seed feito antes de existir usuário).
 public static function create(array $data):User {
  return DB::transaction(function()use($data){
   $first=!User::query()->exists();$user=User::create($data);
   if($first)foreach(self::OWNED as $table)DB::table($table)->whereNull('user_id')->update(['user_id'=>$user->id]);
   self::defaults($user);self::matchUser($user);return $user;
  });
 }
 public static function defaults(User $user):void {
  Tenant::run($user->id,function(){
   foreach(['Alimentação','Supermercado','Transporte','Combustível','Moradia','Saúde','Educação','Compras','Lazer','Assinaturas','Viagens','Outros'] as $name)Category::withTrashed()->firstOrCreate(['name'=>$name,'type'=>'expense'],['color'=>'#2d7abe']);
   foreach(['Salário','Reembolso','Rendimentos','Outras receitas'] as $name)Category::withTrashed()->firstOrCreate(['name'=>$name,'type'=>'income'],['color'=>'#16a34a']);
  });
 }
 // Usuário novo (ou que acabou de informar o CPF): gera convite para as pessoas já cadastradas com o CPF dele.
 public static function matchUser(User $user):void {
  if(!$user->cpf)return;
  Person::withoutGlobalScope('owner')->where('cpf',$user->cpf)->where('user_id','!=',$user->id)->whereNull('linked_user_id')->get()->each(function(Person $p)use($user){$p->forceFill(['linked_user_id'=>$user->id,'link_status'=>'pending','link_responded_at'=>null])->save();});
 }
 // Pessoa criada/alterada: ajusta o vínculo conforme o CPF (sem salvar). Retorna true se um convite novo foi gerado.
 public static function matchPerson(Person $person):bool {
  $target=$person->cpf?User::where('cpf',$person->cpf)->where('id','!=',$person->user_id??Tenant::id())->value('id'):null;
  if($target===$person->linked_user_id)return false;
  $person->forceFill(['linked_user_id'=>$target,'link_status'=>$target?'pending':null,'link_responded_at'=>null]);return $target!==null;
 }
 // Convites pendentes e compartilhamentos aceitos do usuário.
 public static function invites(int $userId):array {
  return DB::table('people as p')->join('users as u','u.id','=','p.user_id')->where('p.linked_user_id',$userId)->whereNull('p.deleted_at')->whereIn('p.link_status',['pending','accepted'])
   ->selectRaw('p.id,p.name,p.link_status as status,u.name as owner,(select count(*) from transactions t where t.person_id=p.id and t.deleted_at is null) as total')->orderBy('p.name')->get()->all();
 }
 // A planilha do Google pertence a um único usuário: GOOGLE_SHEETS_OWNER_EMAIL ou, na falta, o primeiro cadastrado.
 public static function sheetOwner():?User {
  $email=config('services.google_sheets.owner_email');
  return ($email?User::where('email',$email)->first():null)??User::orderBy('id')->first();
 }
}

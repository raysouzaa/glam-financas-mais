<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
class Transaction extends Model {
 use HasUuids,SoftDeletes,BelongsToUser;
 protected $fillable=['person_id','category_id','financial_account_id','credit_card_id','type','payment_method','description','amount_cents','installments_count','start_month','notes','origin'];
 protected function casts():array{return ['start_month'=>'date:Y-m-d','amount_cents'=>'integer','installments_count'=>'integer'];}
 public function person(){return $this->belongsTo(Person::class)->withoutGlobalScope('owner');}
 public function category(){return $this->belongsTo(Category::class)->withoutGlobalScope('owner');}
 public function creditCard(){return $this->belongsTo(CreditCard::class)->withoutGlobalScope('owner');}
 public function financialAccount(){return $this->belongsTo(FinancialAccount::class)->withoutGlobalScope('owner');}
 public function owner(){return $this->belongsTo(User::class,'user_id');}
 // Lançamentos do usuário + os compartilhados com ele (somente leitura), via pessoa vinculada pelo CPF.
 public function scopeVisibleTo($query,int $userId){return $query->withoutGlobalScope('owner')->where(fn($w)=>$w->where('transactions.user_id',$userId)->orWhereIn('transactions.person_id',Person::sharedWith($userId)));}
 public function installments(){return $this->hasMany(Installment::class);}
}

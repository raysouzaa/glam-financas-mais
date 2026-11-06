<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
class Transaction extends Model {
 use HasUuids,SoftDeletes;
 protected $fillable=['person_id','category_id','financial_account_id','credit_card_id','type','payment_method','description','amount_cents','installments_count','start_month','notes','origin'];
 protected function casts():array{return ['start_month'=>'date:Y-m-d','amount_cents'=>'integer','installments_count'=>'integer'];}
 public function person(){return $this->belongsTo(Person::class);}
 public function category(){return $this->belongsTo(Category::class);}
 public function creditCard(){return $this->belongsTo(CreditCard::class);}
 public function financialAccount(){return $this->belongsTo(FinancialAccount::class);}
 public function installments(){return $this->hasMany(Installment::class);}
}

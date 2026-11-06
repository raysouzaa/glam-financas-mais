<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Installment extends Model {protected $fillable=['transaction_id','number','due_month','amount_cents','paid','paid_at'];protected function casts():array{return ['due_month'=>'date:Y-m-d','paid'=>'boolean'];}}

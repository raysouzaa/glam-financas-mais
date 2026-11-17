<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
class FinancialAccount extends Model {use HasUuids,SoftDeletes;protected $fillable=['user_id','name','type','opening_balance_cents'];}

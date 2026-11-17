<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
class CreditCard extends Model {use HasUuids,SoftDeletes;protected $fillable=['user_id','name','last_four','closing_day','due_day','limit_cents'];}

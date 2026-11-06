<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
class Person extends Model {use HasUuids,SoftDeletes;protected $fillable=['name','active'];protected function casts():array{return ['active'=>'boolean'];}}

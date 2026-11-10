<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
class Category extends Model {use HasUuids,SoftDeletes,BelongsToUser;protected $fillable=['name','type','color'];}

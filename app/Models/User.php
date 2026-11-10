<?php
namespace App\Models;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
class User extends Authenticatable {use Notifiable;protected $fillable=['name','email','cpf','password'];protected $hidden=['password','remember_token','cpf'];protected function casts():array{return ['password'=>'hashed'];}}

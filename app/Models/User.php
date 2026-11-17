<?php
namespace App\Models;

use App\Notifications\ConfirmarConta;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements MustVerifyEmail
{
    use Notifiable;

    protected $fillable = ['name', 'email', 'password'];
    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return ['password' => 'hashed', 'email_verified_at' => 'datetime', 'is_admin' => 'boolean'];
    }

    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new ConfirmarConta);
    }
}

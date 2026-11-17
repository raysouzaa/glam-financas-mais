<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/** Restringe a rota a usuários com is_admin (definido pelo finance:create-admin / finance:admin). */
class EnsureAdmin
{
    public function handle(Request $request, Closure $next)
    {
        abort_unless($request->user()?->is_admin, 403, 'Acesso restrito a administradores.');
        return $next($request);
    }
}

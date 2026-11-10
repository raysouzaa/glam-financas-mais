<?php
namespace App\Http\Middleware;
use Illuminate\Http\Request;
use Inertia\Middleware;
class HandleInertiaRequests extends Middleware {
 protected $rootView='app';
 public function share(Request $request): array {
  return array_merge(parent::share($request), ['auth'=>['user'=>($u=$request->user())?$u->only('id','name','email')+['needs_cpf'=>!$u->cpf]:null],'invites'=>fn()=>$u?\App\Services\Accounts::invites((int)$u->id):[], 'flash'=>['success'=>fn()=>$request->session()->get('success'),'error'=>fn()=>$request->session()->get('error')]]);
 }
}

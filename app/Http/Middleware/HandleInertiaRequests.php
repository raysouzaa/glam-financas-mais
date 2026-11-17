<?php
namespace App\Http\Middleware;
use Illuminate\Http\Request;
use Inertia\Middleware;
class HandleInertiaRequests extends Middleware {
 protected $rootView='app';
 public function share(Request $request): array {
  return array_merge(parent::share($request), ['auth'=>['user'=>($u=$request->user())?$u->only('id','name','email','is_admin')+['pessoa'=>$u->is_admin?null:$u->pessoa?->name]:null], 'registrationEnabled'=>(bool)config('auth.registration.enabled'), 'flash'=>['success'=>fn()=>$request->session()->get('success'),'error'=>fn()=>$request->session()->get('error'),'verify'=>fn()=>$request->session()->get('verify')]]);
 }
}

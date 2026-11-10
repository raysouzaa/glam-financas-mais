<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use App\Services\{Accounts,Audit};
use App\Support\Cpf;
use Illuminate\Validation\Rule;
class AuthController {
 public function loginPage(){return Inertia::render('Login');}
 public function login(Request $request){$data=$request->validate(['email'=>'required|email','password'=>'required|string']);if(!Auth::attempt($data)){Audit::write('failed_login','users',null,null,['email'=>$data['email']]);return back()->withErrors(['email'=>'E-mail ou senha incorretos.']);}$request->session()->regenerate();Audit::write('login','users',(string)Auth::id(),null,['email'=>Auth::user()->email]);return redirect()->route('dashboard');}
 public function registerPage(){return Inertia::render('Register');}
 public function register(Request $request){
  $request->merge(['cpf'=>Cpf::digits($request->input('cpf'))]);
  $data=$request->validate(['name'=>'required|string|max:120','email'=>['required','email','max:255',Rule::unique('users','email')],'cpf'=>['required',Cpf::rule(),Rule::unique('users','cpf')],'password'=>'required|string|min:12|confirmed'],['email.unique'=>'Este e-mail já está cadastrado.','cpf.required'=>'Informe o CPF.','cpf.unique'=>'Este CPF já está cadastrado.','password.min'=>'A senha precisa ter pelo menos 12 caracteres.','password.confirmed'=>'A confirmação da senha não confere.']);
  $user=Accounts::create($data);Auth::login($user);$request->session()->regenerate();Audit::write('register','users',(string)$user->id,null,['email'=>$user->email]);
  return redirect()->route('dashboard')->with('success','Conta criada. Boas-vindas!');
 }
 // Usuários antigos (criados antes do CPF) informam o CPF uma única vez pelo painel.
 public function setCpf(Request $request){
  $user=$request->user();abort_if((bool)$user->cpf,403);$request->merge(['cpf'=>Cpf::digits($request->input('cpf'))]);
  $data=$request->validate(['cpf'=>['required',Cpf::rule(),Rule::unique('users','cpf')]],['cpf.required'=>'Informe o CPF.','cpf.unique'=>'Este CPF já está cadastrado.']);
  \Illuminate\Support\Facades\DB::transaction(function()use($user,$data){$user->update($data);Accounts::matchUser($user);Audit::write('update','users',(string)$user->id,null,['cpf'=>'informado']);});
  return back()->with('success','CPF salvo.');
 }
 public function logout(Request $request){Audit::write('logout','users',(string)Auth::id(),['email'=>Auth::user()->email],null);Auth::logout();$request->session()->invalidate();$request->session()->regenerateToken();return redirect()->route('login');}
}

<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use App\Services\Audit;
class AuthController {
 public function loginPage(){return Inertia::render('Login');}
 public function login(Request $request){$data=$request->validate(['email'=>'required|email','password'=>'required|string']);if(!Auth::attempt($data)){Audit::write('failed_login','users',null,null,['email'=>$data['email']]);return back()->withErrors(['email'=>'E-mail ou senha incorretos.']);}$request->session()->regenerate();Audit::write('login','users',(string)Auth::id(),null,['email'=>Auth::user()->email]);return redirect()->route('dashboard');}
 public function logout(Request $request){Audit::write('logout','users',(string)Auth::id(),['email'=>Auth::user()->email],null);Auth::logout();$request->session()->invalidate();$request->session()->regenerateToken();return redirect()->route('login');}
}

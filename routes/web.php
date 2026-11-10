<?php
use App\Http\Controllers\{AuthController,FinanceController};
use Illuminate\Support\Facades\Route;
Route::middleware('guest')->group(function(){Route::get('/login',[AuthController::class,'loginPage'])->name('login');Route::post('/login',[AuthController::class,'login'])->middleware('throttle:5,1');Route::get('/registrar',[AuthController::class,'registerPage'])->name('register');Route::post('/registrar',[AuthController::class,'register'])->middleware('throttle:5,1');});
Route::middleware('auth')->group(function(){
 Route::post('/logout',[AuthController::class,'logout'])->name('logout');
 Route::get('/',[FinanceController::class,'dashboard'])->name('dashboard');
 Route::get('/cadastros',[FinanceController::class,'manage'])->name('manage');
 Route::post('/lancamentos',[FinanceController::class,'storeTransaction'])->name('transactions.store');
 Route::put('/lancamentos/{transaction}',[FinanceController::class,'updateTransaction'])->name('transactions.update');
 Route::delete('/lancamentos/{transaction}',[FinanceController::class,'destroyTransaction'])->name('transactions.destroy');
 Route::patch('/parcelas/{installment}',[FinanceController::class,'paid'])->name('installments.paid');
 Route::post('/cadastros/{kind}',[FinanceController::class,'storeReference'])->name('references.store');
 Route::put('/cadastros/{kind}/{id}',[FinanceController::class,'updateReference'])->name('references.update');
 Route::delete('/cadastros/{kind}/{id}',[FinanceController::class,'destroyReference'])->name('references.destroy');
 Route::post('/perfil/cpf',[AuthController::class,'setCpf'])->middleware('throttle:10,1')->name('profile.cpf');
 Route::post('/convites/{person}',[FinanceController::class,'respondInvite'])->name('invites.respond');
 Route::get('/auditoria',[FinanceController::class,'audit'])->name('audit');
});

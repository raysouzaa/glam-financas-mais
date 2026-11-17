<?php
use App\Http\Controllers\{AuthController,FinanceController,RegisterController,CardDebtImportController,PasswordResetController,ProfileController};
use Illuminate\Support\Facades\Route;
Route::middleware('guest')->group(function(){
 Route::get('/login',[AuthController::class,'loginPage'])->name('login');Route::post('/login',[AuthController::class,'login'])->middleware('throttle:5,1');
 Route::get('/criar-conta',[RegisterController::class,'page'])->name('register');Route::post('/criar-conta',[RegisterController::class,'store'])->middleware('throttle:5,1');
 // Esqueci minha senha: identifica por e-mail ou CPF (com CPF, a pessoa confirma o e-mail antes do envio).
 Route::get('/esqueci-senha',[PasswordResetController::class,'page'])->name('password.request');
 Route::post('/esqueci-senha',[PasswordResetController::class,'identify'])->middleware('throttle:5,1')->name('password.email');
 Route::post('/esqueci-senha/confirmar',[PasswordResetController::class,'confirm'])->middleware('throttle:5,1')->name('password.confirm-email');
 Route::post('/esqueci-senha/cancelar',[PasswordResetController::class,'cancel'])->name('password.cancel');
 Route::get('/redefinir-senha/{token}',[PasswordResetController::class,'resetPage'])->name('password.reset');
 Route::post('/redefinir-senha',[PasswordResetController::class,'reset'])->middleware('throttle:5,1')->name('password.update');
 Route::post('/email/reenviar',[RegisterController::class,'resend'])->middleware('throttle:3,1')->name('verification.send');
});
// Link do e-mail de confirmação: assinado e com validade (a assinatura é conferida no controller).
Route::get('/email/confirmar/{id}/{hash}',[RegisterController::class,'verify'])->middleware('throttle:6,1')->name('verification.verify');
Route::middleware('auth')->group(function(){
 Route::post('/logout',[AuthController::class,'logout'])->name('logout');
 Route::get('/',[FinanceController::class,'dashboard'])->name('dashboard');
 Route::get('/cadastros',[FinanceController::class,'manage'])->name('manage');
 // Cada conta gerencia o próprio financeiro; os controllers conferem o dono (user_id) em toda leitura e escrita.
 Route::post('/lancamentos',[FinanceController::class,'storeTransaction'])->name('transactions.store');
 Route::put('/lancamentos/{transaction}',[FinanceController::class,'updateTransaction'])->name('transactions.update');
 Route::delete('/lancamentos/{transaction}',[FinanceController::class,'destroyTransaction'])->name('transactions.destroy');
 Route::patch('/parcelas/{installment}',[FinanceController::class,'paid'])->name('installments.paid');
 Route::post('/cadastros/{kind}',[FinanceController::class,'storeReference'])->name('references.store');
 Route::put('/cadastros/{kind}/{id}',[FinanceController::class,'updateReference'])->whereUuid('id')->name('references.update');
 Route::delete('/cadastros/{kind}/{id}',[FinanceController::class,'destroyReference'])->whereUuid('id')->name('references.destroy');
 // Meu perfil: dados da conta e vínculos com quem cadastrou uma pessoa com o CPF do usuário.
 Route::get('/perfil',[ProfileController::class,'page'])->name('profile');
 Route::post('/perfil/vinculos/{dono}',[ProfileController::class,'aceitar'])->whereNumber('dono')->name('profile.links.accept');
 Route::delete('/perfil/vinculos/{dono}',[ProfileController::class,'remover'])->whereNumber('dono')->name('profile.links.remove');
 // Funções de sistema, só para admin: auditoria e importação da planilha de dívidas (sempre nos dados do próprio admin).
 Route::get('/auditoria',[FinanceController::class,'audit'])->middleware(\App\Http\Middleware\EnsureAdmin::class)->name('audit');
 Route::middleware(\App\Http\Middleware\EnsureAdmin::class)->prefix('/admin/importar-dividas')->name('card-debts.')->group(function(){
  Route::get('/',[CardDebtImportController::class,'index'])->name('index');
  Route::post('/previa',[CardDebtImportController::class,'preview'])->middleware('throttle:20,1')->name('preview');
  Route::post('/previa/descartar',[CardDebtImportController::class,'discard'])->name('discard');
  Route::post('/',[CardDebtImportController::class,'store'])->name('store');
  Route::delete('/{id}',[CardDebtImportController::class,'destroy'])->whereUuid('id')->name('destroy');
 });
});

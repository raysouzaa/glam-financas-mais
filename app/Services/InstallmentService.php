<?php
namespace App\Services;
use App\Models\Transaction;
use Carbon\CarbonImmutable;
class InstallmentService {
 public function rebuild(Transaction $transaction):void {
  $previousPaid=$transaction->installments()->where('paid',true)->get()->keyBy('number');
  // Não reescrever parcelas pagas; alteração financeira deve ser conciliada antes.
  if($previousPaid->isNotEmpty()) throw new \DomainException('Existem parcelas pagas. Desmarque-as antes de alterar este lançamento.');
  $transaction->installments()->delete();
  $count=(int)$transaction->installments_count;
  $base=intdiv((int)$transaction->amount_cents,$count);
  $remainder=(int)$transaction->amount_cents%$count;
  $month=CarbonImmutable::parse($transaction->start_month)->startOfMonth();
  for($n=1;$n<=$count;$n++)$transaction->installments()->create(['number'=>$n,'due_month'=>$month->addMonths($n-1)->format('Y-m-d'),'amount_cents'=>$base+($n<=$remainder?1:0)]);
 }
}

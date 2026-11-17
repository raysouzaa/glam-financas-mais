<?php
namespace App\Services;

use App\Models\{Person,Transaction};
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Grava as compras novas de uma planilha de dívidas do cartão já lida pelo CardDebtParser.
 * Usado pela tela /admin/importar-dividas e pelo comando finance:importar-dividas.
 */
class CardDebtImporter
{
    public function __construct(private InstallmentService $installments) {}

    /** IDs que já existem como lançamento (inclusive excluídos no app), em qualquer conta: o ID vem do conteúdo da linha. */
    public function existingIds(array $ids): array
    {
        return Transaction::withTrashed()->whereIn('id', $ids)->pluck('id')->flip()->all();
    }

    /** Pessoas da conta, indexadas pelo nome normalizado (sem acento/maiúsculas). */
    public function peopleByKey(int $userId): array
    {
        $out = [];
        foreach (Person::withTrashed()->where('user_id', $userId)->orderBy('deleted_at')->get() as $p) $out[XlsxReader::key($p->name)] ??= $p;
        return $out;
    }

    /**
     * @param array $sheet resultado do CardDebtParser::parse
     * @return array{id:?string,created:int,skipped:int,total_cents:int} id = null quando não havia nada novo
     */
    public function import(array $sheet, string $fileName, string $fileHash, ?string $creditCardId, ?string $categoryId, int $userId): array
    {
        $existing = $this->existingIds(array_column($sheet['rows'], 'id'));
        $new = array_values(array_filter($sheet['rows'], fn($r) => !isset($existing[$r['id']])));
        $skipped = count($sheet['rows']) - count($new);
        $total = array_sum(array_column($new, 'amount_cents'));
        if (!$new) return ['id' => null, 'created' => 0, 'skipped' => $skipped, 'total_cents' => 0];

        $importId = (string) Str::uuid();
        DB::transaction(function () use ($importId, $sheet, $new, $skipped, $total, $fileName, $fileHash, $creditCardId, $categoryId, $userId) {
            $row = [
                'id' => $importId, 'file_name' => $fileName, 'file_hash' => $fileHash,
                'credit_card_id' => $creditCardId, 'category_id' => $categoryId,
                'rows_in_file' => count($sheet['rows']), 'created_count' => count($new),
                'skipped_count' => $skipped, 'total_cents' => $total,
                'user_id' => $userId, 'created_at' => now(), 'updated_at' => now(),
            ];
            DB::table('card_debt_imports')->insert($row);

            $people = $this->peopleByKey($userId);
            foreach ($new as $r) {
                $key = XlsxReader::key($r['person']);
                $person = $people[$key] ??= $this->createPerson($r['person'], $userId);
                if ($person->trashed()) {
                    $person->restore();
                    Audit::write('restore', 'people', $person->id, null, $person->toArray(), 'import');
                }
                $t = new Transaction();
                $t->id = $r['id'];
                $t->fill([
                    'user_id' => $userId,
                    'person_id' => $person->id,
                    'category_id' => $categoryId,
                    'credit_card_id' => $creditCardId,
                    'financial_account_id' => null,
                    'type' => 'expense',
                    'payment_method' => 'credit_card',
                    'description' => $r['description'],
                    'amount_cents' => $r['amount_cents'],
                    'installments_count' => $r['installments'],
                    'start_month' => $r['start_month'],
                    'notes' => "Importado da planilha {$fileName} (linha {$r['line']}).",
                    'origin' => 'legacy',
                ]);
                $t->save();
                $this->installments->rebuild($t);
                Audit::write('import', 'transactions', $t->id, null, $t->toArray(), 'import');
                DB::table('card_debt_import_items')->insert(['card_debt_import_id' => $importId, 'transaction_id' => $t->id, 'line' => $r['line']]);
            }
            Audit::write('import', 'card_debt_imports', $importId, null, $row, 'import');
        });

        return ['id' => $importId, 'created' => count($new), 'skipped' => $skipped, 'total_cents' => $total];
    }

    private function createPerson(string $name, int $userId): Person
    {
        $p = Person::create(['user_id' => $userId, 'name' => $name]);
        Audit::write('create', 'people', $p->id, null, $p->toArray(), 'import');
        return $p;
    }
}

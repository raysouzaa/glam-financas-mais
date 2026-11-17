<?php
namespace App\Http\Controllers;

use App\Models\{Category,CreditCard,Transaction};
use App\Services\{Audit,CardDebtImporter,CardDebtParser,XlsxReader};
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB,Storage};
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * Importação da planilha "Dívidas do Cartão".
 * Fluxo: upload → prévia (nada é gravado) → confirmação cria uma despesa no cartão por compra,
 * já dividida em parcelas. Compras que já existem (mesmo ID determinístico) são puladas, então
 * dá para reenviar a planilha atualizada. Uma importação pode ser desfeita enquanto nada estiver pago.
 */
class CardDebtImportController
{
    private const SESSION = 'card_debt_preview';
    private const DIR = 'imports/dividas-cartao';

    public function index(CardDebtParser $parser)
    {
        $imports = DB::table('card_debt_imports as i')
            ->leftJoin('credit_cards as c', 'c.id', '=', 'i.credit_card_id')
            ->leftJoin('users as u', 'u.id', '=', 'i.user_id')
            ->select('i.*', 'c.name as card_name', 'u.name as user_name')
            ->where('i.user_id', auth()->id())
            ->orderByDesc('i.created_at')->get();
        $paid = DB::table('card_debt_import_items as it')
            ->join('installments as p', 'p.transaction_id', '=', 'it.transaction_id')
            ->where('p.paid', true)->distinct()->pluck('it.card_debt_import_id')->flip();
        foreach ($imports as $i) $i->locked = isset($paid[$i->id]);

        $last = $imports->first();

        return Inertia::render('CardDebts', [
            'imports' => $imports,
            'preview' => $this->previewData($parser),
            'cards' => CreditCard::where('user_id', auth()->id())->orderBy('name')->get(['id', 'name', 'last_four']),
            'categories' => Category::where('user_id', auth()->id())->where('type', 'expense')->orderBy('name')->get(['id', 'name']),
            'defaults' => ['credit_card_id' => $last?->credit_card_id, 'category_id' => $last?->category_id],
        ]);
    }

    public function preview(Request $request, CardDebtParser $parser)
    {
        $request->validate(['file' => 'required|file|max:5120|extensions:xlsx'], [
            'file.required' => 'Selecione a planilha de dívidas do cartão.',
            'file.extensions' => 'Envie o arquivo no formato .xlsx.',
            'file.max' => 'O arquivo pode ter no máximo 5 MB.',
            'file.uploaded' => 'Não foi possível enviar o arquivo. Tente de novo.',
        ]);
        $file = $request->file('file');
        try {
            $parser->parse($file->getRealPath());
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['file' => $e->getMessage()]);
        }
        $hash = hash_file('sha256', $file->getRealPath());
        $this->discardPending();
        $path = $file->storeAs(self::DIR, $hash.'.xlsx', 'local');
        $request->session()->put(self::SESSION, ['hash' => $hash, 'path' => $path, 'file_name' => Str::limit($file->getClientOriginalName(), 255, '')]);

        return redirect()->route('card-debts.index');
    }

    public function discard()
    {
        $this->discardPending();

        return redirect()->route('card-debts.index');
    }

    public function store(Request $request, CardDebtParser $parser, CardDebtImporter $importer)
    {
        $pending = $request->session()->get(self::SESSION);
        if (!$pending || !Storage::disk('local')->exists($pending['path'])) {
            return back()->withErrors(['file' => 'A prévia expirou. Envie a planilha novamente.']);
        }
        $data = $request->validate([
            'hash' => 'required|string|size:64',
            'credit_card_id' => ['nullable', Rule::exists('credit_cards', 'id')->where('user_id', auth()->id())->whereNull('deleted_at')],
            'category_id' => ['nullable', Rule::exists('categories', 'id')->where('user_id', auth()->id())->where('type', 'expense')->whereNull('deleted_at')],
        ]);
        if (!hash_equals($pending['hash'], $data['hash'])) {
            return back()->withErrors(['file' => 'A prévia mudou. Confira os dados e confirme novamente.']);
        }

        $sheet = $parser->parse(Storage::disk('local')->path($pending['path']));
        $result = $importer->import($sheet, $pending['file_name'], $pending['hash'], $data['credit_card_id'] ?? null, $data['category_id'] ?? null, auth()->id());
        if (!$result['id']) {
            return back()->withErrors(['file' => 'Nenhuma compra nova: todas as linhas desta planilha já foram importadas.']);
        }

        $this->discardPending();
        $msg = $result['created'].' compra(s) importada(s), somando '.$this->brl($result['total_cents']).'.';
        if ($result['skipped']) $msg .= " {$result['skipped']} já existiam e foram puladas.";

        return redirect()->route('card-debts.index')->with('success', $msg);
    }

    public function destroy(string $id)
    {
        $import = DB::table('card_debt_imports')->where('id', $id)->where('user_id', auth()->id())->first();
        abort_unless($import, 404);
        $ids = DB::table('card_debt_import_items')->where('card_debt_import_id', $id)->pluck('transaction_id');
        if (DB::table('installments')->whereIn('transaction_id', $ids)->where('paid', true)->exists()) {
            return back()->withErrors(['delete' => 'Há parcelas desta importação marcadas como pagas. Desmarque-as antes de desfazer.']);
        }
        DB::transaction(function () use ($import, $ids) {
            // Exclusão definitiva: assim a mesma compra pode ser importada de novo depois.
            foreach (Transaction::withTrashed()->whereIn('id', $ids)->get() as $t) {
                $old = $t->toArray();
                $t->forceDelete();
                Audit::write('delete', 'transactions', $t->id, $old, null, 'import');
            }
            DB::table('card_debt_imports')->where('id', $import->id)->delete();
            Audit::write('delete', 'card_debt_imports', $import->id, (array) $import, null, 'import');
        });

        return back()->with('success', 'Importação desfeita: '.count($ids).' lançamento(s) removido(s). O histórico fica na auditoria.');
    }

    // ---- auxiliares ----

    private function previewData(CardDebtParser $parser): ?array
    {
        $pending = session(self::SESSION);
        if (!$pending || !Storage::disk('local')->exists($pending['path'])) return null;
        try {
            $sheet = $parser->parse(Storage::disk('local')->path($pending['path']));
        } catch (\InvalidArgumentException) {
            $this->discardPending();
            return null;
        }

        $trashed = Transaction::onlyTrashed()->whereIn('id', array_column($sheet['rows'], 'id'))->pluck('id')->flip();
        $importer = app(CardDebtImporter::class);
        $existing = $importer->existingIds(array_column($sheet['rows'], 'id'));
        $known = $importer->peopleByKey((int) auth()->id());
        $newPeople = [];
        $byPerson = [];
        $byMonth = [];
        foreach ($sheet['rows'] as &$r) {
            $r['status'] = isset($trashed[$r['id']]) ? 'deleted' : (isset($existing[$r['id']]) ? 'exists' : 'new');
            $key = XlsxReader::key($r['person']);
            if (!isset($known[$key])) $newPeople[$key] = $r['person'];
            $p = &$byPerson[$r['person']];
            $p ??= ['person' => $r['person'], 'total_cents' => 0, 'new_cents' => 0, 'rows' => 0];
            $p['total_cents'] += $r['amount_cents'];
            $p['rows']++;
            if ($r['status'] === 'new') $p['new_cents'] += $r['amount_cents'];
            unset($p);
            // Mesma divisão do InstallmentService: centavos que sobram vão para as primeiras parcelas.
            $base = intdiv($r['amount_cents'], $r['installments']);
            $rest = $r['amount_cents'] % $r['installments'];
            $month = CarbonImmutable::parse($r['start_month']);
            for ($n = 0; $n < $r['installments']; $n++) {
                $ym = $month->addMonths($n)->format('Y-m');
                $byMonth[$ym][$r['person']] = ($byMonth[$ym][$r['person']] ?? 0) + $base + ($n < $rest ? 1 : 0);
            }
            $r['monthly_cents'] = $base + ($rest ? 1 : 0);
            $r['end_month'] = $month->addMonths($r['installments'] - 1)->format('Y-m-d');
        }
        unset($r);
        ksort($byMonth);

        $counts = array_count_values(array_column($sheet['rows'], 'status'));
        $warnings = [];
        if ($newPeople) $warnings[] = 'Pessoa(s) nova(s) que serão cadastradas: '.implode(', ', $newPeople).'.';
        if (!empty($counts['deleted'])) $warnings[] = "{$counts['deleted']} compra(s) já foram importadas e depois excluídas no app; elas não serão recriadas.";
        if ($sheet['invalid']) $warnings[] = count($sheet['invalid']).' linha(s) com problema serão ignoradas (veja abaixo).';

        return [
            'hash' => $pending['hash'],
            'file_name' => $pending['file_name'],
            'sheet' => $sheet['sheet'],
            'rows' => $sheet['rows'],
            'invalid' => $sheet['invalid'],
            'counts' => ['new' => $counts['new'] ?? 0, 'exists' => $counts['exists'] ?? 0, 'deleted' => $counts['deleted'] ?? 0],
            'new_cents' => array_sum(array_map(fn($r) => $r['status'] === 'new' ? $r['amount_cents'] : 0, $sheet['rows'])),
            'by_person' => array_values($byPerson),
            'by_month' => array_map(fn($ym) => ['month' => $ym, 'people' => $byMonth[$ym], 'total_cents' => array_sum($byMonth[$ym])], array_keys($byMonth)),
            'warnings' => $warnings,
        ];
    }

    /** Apaga o arquivo da prévia pendente. */
    private function discardPending(): void
    {
        $pending = session()->pull(self::SESSION);
        if ($pending) Storage::disk('local')->delete($pending['path']);
    }

    private function brl(int $cents): string
    {
        return 'R$ '.number_format($cents / 100, 2, ',', '.');
    }
}

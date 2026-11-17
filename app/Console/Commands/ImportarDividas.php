<?php
namespace App\Console\Commands;

use App\Models\{Category,CreditCard};
use App\Services\{CardDebtImporter,CardDebtParser,XlsxReader};
use Illuminate\Console\Command;

/** Mesmo processo da tela /admin/importar-dividas, pelo terminal. */
class ImportarDividas extends Command
{
    protected $signature = 'finance:importar-dividas
        {arquivo : caminho do .xlsx (dentro do container, ex.: storage/app/Dividas.xlsx)}
        {--cartao= : nome do cartão cadastrado (opcional)}
        {--categoria= : nome da categoria de despesa (opcional)}
        {--simular : só mostra o que seria importado}';
    protected $description = 'Importa a planilha de dívidas do cartão (aba Lançamentos); compras já importadas são puladas';

    public function handle(CardDebtParser $parser, CardDebtImporter $importer): int
    {
        $path = $this->argument('arquivo');
        if (!is_readable($path)) { $this->error("Arquivo não encontrado: {$path}"); return self::FAILURE; }

        $card = $category = null;
        if ($name = $this->option('cartao')) {
            $card = CreditCard::get()->first(fn($c) => XlsxReader::key($c->name) === XlsxReader::key($name));
            if (!$card) { $this->error("Cartão \"{$name}\" não encontrado."); return self::FAILURE; }
        }
        if ($name = $this->option('categoria')) {
            $category = Category::where('type', 'expense')->get()->first(fn($c) => XlsxReader::key($c->name) === XlsxReader::key($name));
            if (!$category) { $this->error("Categoria de despesa \"{$name}\" não encontrada."); return self::FAILURE; }
        }

        try {
            $sheet = $parser->parse($path);
        } catch (\InvalidArgumentException $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }
        foreach ($sheet['invalid'] as $i) $this->warn("Linha {$i['line']} ignorada: {$i['reason']} ({$i['raw']})");

        $existing = $importer->existingIds(array_column($sheet['rows'], 'id'));
        $new = array_filter($sheet['rows'], fn($r) => !isset($existing[$r['id']]));
        $brl = fn(int $c) => 'R$ '.number_format($c / 100, 2, ',', '.');
        $this->line(sprintf('Aba "%s": %d compra(s); %d nova(s) somando %s; %d já importada(s).',
            $sheet['sheet'], count($sheet['rows']), count($new), $brl(array_sum(array_column($new, 'amount_cents'))), count($existing)));
        if ($this->option('simular')) return self::SUCCESS;
        if (!$new) { $this->info('Nada novo para importar.'); return self::SUCCESS; }

        $r = $importer->import($sheet, basename($path), hash_file('sha256', $path), $card?->id, $category?->id, null);
        $this->info("Importação {$r['id']}: {$r['created']} compra(s) criada(s), ".$brl($r['total_cents'])."; {$r['skipped']} pulada(s).");

        return self::SUCCESS;
    }
}

<?php
namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Ramsey\Uuid\Uuid;

/**
 * Lê a planilha "Dívidas do Cartão" (aba "Lançamentos"): uma compra por linha com
 * Pessoa, Descrição, Valor Total da Compra, Parcelas e Mês Início.
 * As colunas são achadas pelo texto do cabeçalho, não pela posição; as colunas de meses
 * e as linhas de total da planilha são ignoradas (o app recalcula as parcelas).
 *
 * Cada compra recebe um ID determinístico calculado pelo conteúdo da linha, para que
 * reimportar a planilha (com compras novas acrescentadas) traga só o que ainda não existe.
 */
class CardDebtParser
{
    /** Namespace UUID v5 das compras importadas desta planilha. */
    private const UUID_NS = '3f6c2a1e-9b7d-5e4f-8a1b-2c3d4e5f6a7b';
    private const COLS = ['pessoa', 'descricao', 'valor total', 'parcelas', 'mes inicio'];

    public function __construct(private XlsxReader $reader) {}

    /**
     * @return array{sheet:string,rows:array<int,array>,invalid:array<int,array{line:int,reason:string,raw:string}>}
     *   rows: line, person, description, amount_cents, installments, start_month (Y-m-01), id
     */
    public function parse(string $path): array
    {
        $sheets = $this->reader->read($path);
        [$sheetName, $rows, $headerLine, $cols] = $this->locate($sheets);

        $out = [];
        $invalid = [];
        $seen = [];
        foreach ($rows as $line => $row) {
            if ($line <= $headerLine) continue;
            $person = Str::squish((string) ($row[$cols['pessoa']] ?? ''));
            $description = Str::squish((string) ($row[$cols['descricao']] ?? ''));
            $amountRaw = trim((string) ($row[$cols['valor total']] ?? ''));
            $installmentsRaw = trim((string) ($row[$cols['parcelas']] ?? ''));
            $monthRaw = trim((string) ($row[$cols['mes inicio']] ?? ''));

            if ($person === '' && $description === '' && $amountRaw === '') continue;      // linha vazia
            if (str_starts_with(XlsxReader::key($person), 'total')) continue;            // linhas "Total do Mês", "Total Vic"...
            $raw = implode(' · ', array_filter([$person, $description, $amountRaw], fn($v) => $v !== ''));

            try {
                if ($person === '') throw new \InvalidArgumentException('sem pessoa');
                if ($description === '') throw new \InvalidArgumentException('sem descrição');
                if ($amountRaw === '') throw new \InvalidArgumentException('sem valor');
                $cents = (int) round(XlsxReader::number($amountRaw) * 100);
                if ($cents <= 0) throw new \InvalidArgumentException('valor precisa ser maior que zero');
                $installments = $installmentsRaw === '' ? 1 : XlsxReader::number($installmentsRaw);
                if ($installments != (int) $installments || $installments < 1 || $installments > 120) throw new \InvalidArgumentException('parcelas precisa ser um número inteiro de 1 a 120');
                $installments = (int) $installments;
                if ($monthRaw === '') throw new \InvalidArgumentException('sem mês início');
                $month = CarbonImmutable::parse(XlsxReader::date($monthRaw))->startOfMonth()->format('Y-m-d');
            } catch (\InvalidArgumentException $e) {
                $invalid[] = ['line' => $line, 'reason' => Str::ucfirst($e->getMessage()), 'raw' => Str::limit($raw, 120)];
                continue;
            }

            $description = Str::limit($description, 255, '');
            $person = Str::limit($person, 120, '');
            // Mesma compra repetida no arquivo (ex.: dois estacionamentos iguais no mesmo mês) vira #2, #3...
            $fingerprint = implode('|', [XlsxReader::key($person), XlsxReader::key($description), $cents, $installments, $month]);
            $seen[$fingerprint] = ($seen[$fingerprint] ?? 0) + 1;

            $out[] = [
                'line' => $line,
                'person' => $person,
                'description' => $description,
                'amount_cents' => $cents,
                'installments' => $installments,
                'start_month' => $month,
                'id' => Uuid::uuid5(self::UUID_NS, $fingerprint.'#'.$seen[$fingerprint])->toString(),
            ];
        }

        if (!$out && !$invalid) throw new \InvalidArgumentException("A aba \"{$sheetName}\" não tem nenhuma compra preenchida.");

        return ['sheet' => $sheetName, 'rows' => $out, 'invalid' => $invalid];
    }

    /** Acha a aba e a linha de cabeçalho com Pessoa, Descrição, Valor Total, Parcelas e Mês Início. */
    private function locate(array $sheets): array
    {
        // Prefere a aba "Lançamentos"; senão, a primeira que tiver o cabeçalho.
        uksort($sheets, fn($a, $b) => ($b === 'lancamentos') <=> ($a === 'lancamentos'));
        foreach ($sheets as $sheet) {
            foreach (array_slice($sheet['rows'], 0, 15, true) as $line => $row) {
                $cols = XlsxReader::headerColumns($row, self::COLS);
                if (count($cols) === count(self::COLS)) return [$sheet['name'], $sheet['rows'], $line, $cols];
            }
        }
        throw new \InvalidArgumentException('Não encontrei a tabela de compras. A planilha precisa de uma aba (ex.: "Lançamentos") com as colunas Pessoa, Descrição, Valor Total, Parcelas e Mês Início.');
    }
}

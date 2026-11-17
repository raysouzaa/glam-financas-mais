<?php
namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

/**
 * Leitor mínimo de .xlsx (OOXML) sem dependências: abas, textos compartilhados e valores das células.
 * Lê o valor gravado na célula (para fórmulas, o último resultado calculado pelo Excel).
 */
class XlsxReader
{
    private const NS = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
    private const MAX_ENTRY_BYTES = 10 * 1024 * 1024;

    /**
     * Abre o arquivo e devolve as linhas de todas as abas.
     * @return array<string,array{name:string,rows:array<int,array<string,string>>}> chave = nome normalizado da aba
     */
    public function read(string $path): array
    {
        $zip = new \ZipArchive();
        if ($zip->open($path, \ZipArchive::RDONLY) !== true) {
            throw new \InvalidArgumentException('O arquivo não é uma planilha .xlsx válida.');
        }
        try {
            $strings = $this->sharedStrings($zip);
            $out = [];
            foreach ($this->sheets($zip) as $key => [$name, $file]) {
                $out[$key] = ['name' => $name, 'rows' => $this->rows($zip, $file, $strings)];
            }
            return $out;
        } finally {
            $zip->close();
        }
    }

    /** Texto normalizado para comparar rótulos: sem acento, minúsculo, espaços simples. */
    public static function key(mixed $v): string
    {
        return Str::of(Str::ascii((string) $v))->lower()->squish()->toString();
    }

    /** Número do Excel ou texto em formato brasileiro ("R$ 1.234,56"). */
    public static function number(mixed $v): float
    {
        $s = trim((string) $v);
        if ($s === '') return 0.0;
        if (!is_numeric($s)) {
            $s = str_replace(['R$', ' ', "\u{A0}", '.'], '', $s);
            $s = str_replace(',', '.', $s);
        }
        if (!is_numeric($s)) throw new \InvalidArgumentException("Valor numérico inválido: \"{$v}\".");
        return (float) $s;
    }

    /** Aceita serial do Excel, dd/mm/aaaa ou aaaa-mm-dd. Devolve Y-m-d. */
    public static function date(mixed $v): string
    {
        $s = trim((string) $v);
        if (is_numeric($s)) {
            $serial = (float) $s;
            if ($serial <= 0 || $serial > 2958465) throw new \InvalidArgumentException('Data inválida.');
            return CarbonImmutable::create(1899, 12, 30, 0, 0, 0, 'UTC')->addDays((int) floor($serial))->format('Y-m-d');
        }
        foreach (['!d/m/Y', '!Y-m-d'] as $format) {
            try {
                $d = CarbonImmutable::createFromFormat($format, $s, 'UTC');
            } catch (\Throwable) {
                continue;
            }
            if ($d && $d->format(ltrim($format, '!')) === $s) return $d->format('Y-m-d');
        }
        throw new \InvalidArgumentException("Data inválida: \"{$s}\".");
    }

    /** Mapeia rótulos (prefixo normalizado) para a letra da coluna. */
    public static function headerColumns(array $row, array $wanted): array
    {
        $cols = [];
        foreach ($row as $col => $value) {
            $k = self::key($value);
            foreach ($wanted as $w) {
                if (!isset($cols[$w]) && $k !== '' && str_starts_with($k, $w)) $cols[$w] = $col;
            }
        }
        return $cols;
    }

    // ---- leitura do pacote OOXML ----

    private function entry(\ZipArchive $zip, string $name): ?string
    {
        $stat = $zip->statName($name);
        if ($stat === false) return null;
        if ($stat['size'] > self::MAX_ENTRY_BYTES) throw new \InvalidArgumentException('Planilha grande demais.');
        $data = $zip->getFromName($name);
        return $data === false ? null : $data;
    }

    private function xml(string $data): \SimpleXMLElement
    {
        $xml = simplexml_load_string($data, \SimpleXMLElement::class, LIBXML_NONET | LIBXML_COMPACT);
        if ($xml === false) throw new \InvalidArgumentException('Conteúdo da planilha corrompido.');
        $xml->registerXPathNamespace('m', self::NS);
        return $xml;
    }

    /** @return array<string,array{0:string,1:string}> nome normalizado => [nome original, caminho no zip] */
    private function sheets(\ZipArchive $zip): array
    {
        $wb = $this->xml($this->entry($zip, 'xl/workbook.xml') ?? throw new \InvalidArgumentException('O arquivo não é uma planilha .xlsx válida.'));
        $rels = $this->xml($this->entry($zip, 'xl/_rels/workbook.xml.rels') ?? '');
        $targets = [];
        foreach ($rels->children() as $rel) {
            $target = (string) $rel['Target'];
            $targets[(string) $rel['Id']] = str_starts_with($target, '/') ? ltrim($target, '/') : 'xl/'.$target;
        }
        $out = [];
        foreach ($wb->xpath('//m:sheets/m:sheet') as $sheet) {
            $rid = (string) $sheet->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')['id'];
            if (isset($targets[$rid])) $out[self::key($sheet['name'])] = [(string) $sheet['name'], $targets[$rid]];
        }
        return $out;
    }

    private function sharedStrings(\ZipArchive $zip): array
    {
        $data = $this->entry($zip, 'xl/sharedStrings.xml');
        if ($data === null) return [];
        $xml = $this->xml($data);
        $out = [];
        foreach ($xml->xpath('/m:sst/m:si') as $si) {
            $si->registerXPathNamespace('m', self::NS);
            $out[] = implode('', array_map('strval', $si->xpath('.//m:t[not(ancestor::m:rPh)]')));
        }
        return $out;
    }

    /** @return array<int,array<string,string>> número da linha => [letra da coluna => valor] */
    private function rows(\ZipArchive $zip, string $path, array $strings): array
    {
        $xml = $this->xml($this->entry($zip, $path) ?? throw new \InvalidArgumentException('Aba da planilha não encontrada.'));
        $rows = [];
        $next = 1;
        foreach ($xml->xpath('//m:sheetData/m:row') as $row) {
            $n = (int) ($row['r'] ?? $next);
            $next = $n + 1;
            $cells = [];
            foreach ($row->c as $c) {
                $col = preg_replace('/\d+/', '', (string) $c['r']);
                $cells[$col] = match ((string) $c['t']) {
                    's' => $strings[(int) $c->v] ?? '',
                    'inlineStr' => implode('', array_map('strval', $c->xpath('.//*[local-name()="t"]'))),
                    default => (string) $c->v,
                };
            }
            $rows[$n] = $cells;
        }
        return $rows;
    }
}

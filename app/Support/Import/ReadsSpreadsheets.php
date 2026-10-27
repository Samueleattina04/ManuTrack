<?php

namespace App\Support\Import;

use App\Support\Numbers;
use DateTimeInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;
use RuntimeException;
use Throwable;

/**
 * Lettura di CSV ed Excel per le importazioni (macchine, ricambi).
 * La classe che lo usa definisce MAX_ROWS e COLUMNS (campo => intestazioni accettate).
 */
trait ReadsSpreadsheets
{
    public static function xlsxAvailable(): bool
    {
        return extension_loaded('zip') && extension_loaded('xmlreader') && class_exists(XlsxReader::class);
    }

    /**
     * Legge il file e restituisce le righe come elenchi di celle (la prima è l'intestazione).
     *
     * @return list<list<mixed>>
     */
    public function read(string $path, string $extension): array
    {
        $extension = strtolower($extension);
        if ($extension === 'xlsx') {
            if (! self::xlsxAvailable()) {
                throw new RuntimeException(__('assets.import.xlsx_unavailable'));
            }

            return $this->readXlsx($path);
        }
        if (in_array($extension, ['csv', 'txt'], true)) {
            return $this->readCsv($path);
        }

        throw new RuntimeException(__('assets.import.type_invalid'));
    }

    /**
     * @return list<list<mixed>>
     */
    private function readXlsx(string $path): array
    {
        $reader = new XlsxReader;
        $rows = [];
        try {
            $reader->open($path);
            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $row) {
                    $rows[] = $row->toArray();
                    if (count($rows) > self::MAX_ROWS + 1) {
                        break;
                    }
                }
                break; // solo il primo foglio
            }
        } catch (Throwable $e) {
            throw new RuntimeException(__('assets.import.unreadable'), previous: $e);
        } finally {
            $reader->close();
        }

        return $rows;
    }

    /**
     * CSV salvati da Excel in italiano: separatore ";" e codifica Windows-1252.
     * Si riconoscono da soli separatore (; , tab) e codifica.
     *
     * @return list<list<mixed>>
     */
    private function readCsv(string $path): array
    {
        $content = (string) file_get_contents($path);
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content) ?? $content;
        if (! mb_check_encoding($content, 'UTF-8')) {
            $content = mb_convert_encoding($content, 'UTF-8', 'Windows-1252');
        }

        $firstLine = strtok($content, "\r\n") ?: '';
        $delimiter = collect([';', ',', "\t"])->sortByDesc(fn ($d) => substr_count($firstLine, $d))->first();

        $handle = fopen('php://temp', 'r+');
        fwrite($handle, $content);
        rewind($handle);
        $rows = [];
        while (($row = fgetcsv($handle, null, $delimiter, '"', '')) !== false) {
            if ($row === [null]) {
                continue; // riga vuota
            }
            $rows[] = $row;
            if (count($rows) > self::MAX_ROWS + 1) {
                break;
            }
        }
        fclose($handle);

        return $rows;
    }

    /**
     * @param  list<mixed>  $header
     * @return array{0: array<string, int>, 1: list<string>}
     */
    private function mapHeader(array $header): array
    {
        $map = [];
        $unknown = [];
        foreach ($header as $index => $title) {
            $key = preg_replace('/[^a-z0-9]/', '', Str::ascii(mb_strtolower(trim((string) $title)))) ?? '';
            $field = collect(self::COLUMNS)->search(fn ($aliases) => in_array($key, $aliases, true));
            if ($field !== false && ! isset($map[$field])) {
                $map[$field] = (int) $index;
            } elseif (trim((string) $title) !== '') {
                $unknown[] = trim((string) $title);
            }
        }

        return [$map, $unknown];
    }

    private function cell(mixed $value): mixed
    {
        if ($value instanceof DateTimeInterface) {
            return Carbon::instance($value)->toDateString();
        }
        if (is_string($value)) {
            $value = trim($value);

            return $value === '' ? null : $value;
        }

        return $value;
    }

    /**
     * Numeri all'italiana ("1.234,50") o all'inglese ("1234.50").
     *
     * @param  list<string>  $messages
     */
    private function number(mixed $value, array &$messages): ?float
    {
        if ($value === null) {
            return null;
        }
        $number = Numbers::parse($value);
        if ($number === null) {
            $messages[] = $this->message('assets.import.number_invalid', ['value' => (string) $value]);
        }

        return $number;
    }

    /**
     * @param  array<string, mixed>  $replace
     */
    private function message(string $key, array $replace = []): string
    {
        $text = __($key, $replace);

        return is_string($text) ? $text : $key;
    }
}

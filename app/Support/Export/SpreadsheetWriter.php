<?php

namespace App\Support\Export;

use DateTimeImmutable;
use DateTimeInterface;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Options;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;

/**
 * Scrive le tabelle in CSV (separatore ";", BOM UTF-8, decimali con la virgola,
 * come le aprono Excel e LibreOffice in italiano) o in Excel (un foglio per
 * tabella, numeri e date come tali). Ogni testo passa da SafeCell.
 */
final class SpreadsheetWriter
{
    public static function xlsxAvailable(): bool
    {
        return extension_loaded('zip') && class_exists(\ZipArchive::class);
    }

    /** Scrive la tabella come CSV sul flusso indicato */
    public function csv(Table $table, mixed $handle): void
    {
        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, array_map(SafeCell::text(...), array_keys($table->columns)), ';', '"', '');
        $types = $table->types();
        foreach ($table->rows() as $row) {
            $cells = [];
            foreach (array_values($row) as $i => $value) {
                $cells[] = $this->csvValue($value, $types[$i] ?? 'text');
            }
            fputcsv($handle, $cells, ';', '"', '');
        }
    }

    /**
     * Scrive le tabelle in un file .xlsx.
     *
     * @param  list<Table>  $tables
     */
    public function xlsx(array $tables, string $path): void
    {
        $options = new Options;
        $options->setTempFolder(storage_path('framework/cache'));
        $writer = new XlsxWriter($options);
        $writer->openToFile($path);

        $bold = (new Style)->setFontBold();
        $styles = [
            'decimal' => (new Style)->setFormat('#,##0.00'),
            'quantity' => (new Style)->setFormat('#,##0.###'),
            'hours' => (new Style)->setFormat('#,##0.0'),
            'percent' => (new Style)->setFormat('0.0'),
            'date' => (new Style)->setFormat('dd/mm/yyyy'),
            'datetime' => (new Style)->setFormat('dd/mm/yyyy hh:mm'),
        ];

        foreach ($tables as $index => $table) {
            $sheet = $index === 0 ? $writer->getCurrentSheet() : $writer->addNewSheetAndMakeItCurrent();
            $sheet->setName(mb_substr(str_replace(['\\', '/', '?', '*', '[', ']', ':'], ' ', $table->name), 0, 31));
            $writer->addRow(new Row(array_map(fn (string $h) => Cell::fromValue(SafeCell::text($h), $bold), array_keys($table->columns))));
            $types = $table->types();
            foreach ($table->rows() as $row) {
                $cells = [];
                foreach (array_values($row) as $i => $value) {
                    $type = $types[$i] ?? 'text';
                    $cells[] = $this->xlsxCell($value, $type, $styles[$type] ?? null);
                }
                $writer->addRow(new Row($cells));
            }
        }
        $writer->close();
    }

    private function csvValue(mixed $value, string $type): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        return match ($type) {
            'int' => (string) (int) $value,
            'decimal' => number_format((float) $value, 2, ',', ''),
            'hours', 'percent' => number_format((float) $value, 1, ',', ''),
            'number' => rtrim(rtrim(number_format((float) $value, 2, ',', ''), '0'), ','),
            'quantity' => rtrim(rtrim(number_format((float) $value, 3, ',', ''), '0'), ','),
            'date' => $value instanceof DateTimeInterface ? $value->format('d/m/Y') : SafeCell::text((string) $value),
            'datetime' => $value instanceof DateTimeInterface ? $value->format('d/m/Y H:i') : SafeCell::text((string) $value),
            default => SafeCell::text(is_scalar($value) ? (string) $value : ''),
        };
    }

    private function xlsxCell(mixed $value, string $type, ?Style $style): Cell
    {
        if ($value === null || $value === '') {
            return Cell::fromValue(null);
        }

        return match ($type) {
            'int' => Cell::fromValue((int) $value),
            'number' => Cell::fromValue(is_int($value) ? $value : (float) $value),
            'decimal', 'hours', 'percent', 'quantity' => Cell::fromValue((float) $value, $style),
            'date', 'datetime' => $value instanceof DateTimeInterface
                ? Cell::fromValue(DateTimeImmutable::createFromInterface($value), $style)
                : Cell::fromValue(SafeCell::text((string) $value)),
            default => Cell::fromValue(SafeCell::text(is_scalar($value) ? (string) $value : '')),
        };
    }
}

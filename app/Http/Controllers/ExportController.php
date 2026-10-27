<?php

namespace App\Http\Controllers;

use App\Models\AssetCategory;
use App\Models\Location;
use App\Support\Audit\Audit;
use App\Support\Export\Exports;
use App\Support\Export\SpreadsheetWriter;
use App\Support\Export\Table;
use App\Support\Filters\AssetFilters;
use App\Support\Filters\PartFilters;
use App\Support\Filters\WorkOrderFilters;
use App\Support\Kpi\KpiCalculator;
use App\Support\Kpi\KpiPeriod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Esportazioni CSV ed Excel degli elenchi e degli indicatori, con i filtri
 * dell'indirizzo della pagina. Serve "Esportare CSV ed Excel" più il permesso
 * di vedere l'elenco (controllati dalle rotte); i costi seguono i loro permessi.
 */
class ExportController extends Controller
{
    public function __construct(private Exports $exports, private SpreadsheetWriter $writer) {}

    public function workOrders(Request $request, string $format): Response
    {
        $filters = WorkOrderFilters::fromQuery($request->query(), $request->user());
        $this->guardSize($filters->query($request->user()));

        return $this->send($request, 'ordini', $format, [$this->exports->workOrders($request->user(), $filters)]);
    }

    public function assets(Request $request, string $format): Response
    {
        $filters = AssetFilters::fromQuery($request->query());
        $this->guardSize($filters->query());

        return $this->send($request, 'macchine', $format, [$this->exports->assets($request->user(), $filters)]);
    }

    public function parts(Request $request, string $format): Response
    {
        $filters = PartFilters::fromQuery($request->query());
        $this->guardSize($filters->query());

        return $this->send($request, 'ricambi', $format, [$this->exports->parts($request->user(), $filters)]);
    }

    public function kpi(Request $request, string $format): Response
    {
        $q = $request->query();
        try {
            $period = KpiPeriod::fromInput((string) ($q['periodo'] ?? '30g'), (string) ($q['dal'] ?? ''), (string) ($q['al'] ?? ''));
        } catch (InvalidArgumentException $e) {
            abort(422, $e->getMessage());
        }
        $location = ctype_digit((string) ($q['ubicazione'] ?? '')) ? (int) $q['ubicazione'] : null;
        $category = ctype_digit((string) ($q['categoria'] ?? '')) ? (int) $q['categoria'] : null;
        $label = implode(' · ', array_filter([
            $location ? Location::labels()[$location] ?? null : null,
            $category ? AssetCategory::query()->whereKey($category)->value('name') : null,
        ])) ?: __('export.kpi.none');

        $tables = $this->exports->kpi($request->user(), new KpiCalculator($period, $location, $category), $label);
        if ($format === 'csv') {
            $part = (string) ($q['parte'] ?? 'riepilogo');
            abort_unless(isset($tables[$part]), 404);
            $tables = [$part => $tables[$part]];
        }
        $name = 'indicatori-'.$period->from->format('Ymd').'-'.$period->to->format('Ymd').($format === 'csv' ? '-'.array_key_first($tables) : '');

        return $this->send($request, $name, $format, array_values($tables), 'indicatori');
    }

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     */
    private function guardSize(Builder $query): void
    {
        $count = (clone $query)->toBase()->getCountForPagination();
        if ($count > Exports::MAX_ROWS) {
            abort(422, __('export.too_many', ['n' => $count, 'max' => Exports::MAX_ROWS]));
        }
    }

    /**
     * @param  list<Table>  $tables
     */
    private function send(Request $request, string $name, string $format, array $tables, ?string $kind = null): Response
    {
        @set_time_limit(300);
        $file = 'manutrack-'.$name.'-'.now()->format('Ymd-Hi').'.'.$format;
        Audit::log('reports.exported', null, ($kind ?? $name).' · '.$format, ['filtri' => $request->query()]);

        if ($format === 'xlsx') {
            abort_unless(SpreadsheetWriter::xlsxAvailable(), 422, __('export.xlsx_unavailable'));
            $path = tempnam(storage_path('framework/cache'), 'export');
            $this->writer->xlsx($tables, (string) $path);

            return response()->download((string) $path, $file, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Cache-Control' => 'no-store, private',
            ])->deleteFileAfterSend();
        }

        return response()->streamDownload(function () use ($tables) {
            $out = fopen('php://output', 'w');
            $this->writer->csv($tables[0], $out);
            fclose($out);
        }, $file, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'no-store, private',
        ]);
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Support\Assets\AssetImport;
use App\Support\Assets\QrCode;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Codici QR delle macchine: apertura della scheda dal QR, file SVG ed etichette da stampare.
 */
class AssetQrController extends Controller
{
    /** Formati delle etichette su foglio A4: colonne, righe, larghezza e altezza in mm */
    public const FORMATS = [
        'piccole' => ['cols' => 3, 'rows' => 8, 'w' => 70, 'h' => 37, 'qr' => 29],
        'medie' => ['cols' => 2, 'rows' => 4, 'w' => 105, 'h' => 74, 'qr' => 52],
        'grandi' => ['cols' => 2, 'rows' => 2, 'w' => 105, 'h' => 148.5, 'qr' => 80],
    ];

    /** Indirizzo stampato nel QR: porta alla scheda (dopo l'accesso, se serve) */
    public function resolve(string $token): RedirectResponse
    {
        abort_unless(preg_match('/^[a-z0-9]{16,40}$/', $token) === 1, 404);
        $asset = Asset::query()->where('qr_token', $token)->first();

        if (! $asset) {
            return redirect()->route('assets.index')->with('error', __('assets.qr.unknown'));
        }

        return redirect()->route('assets.show', $asset);
    }

    public function svg(Asset $asset): Response
    {
        $svg = QrCode::svg($asset->qrUrl(), quiet: 4, title: $asset->code);

        return response($svg, 200, [
            'Content-Type' => 'image/svg+xml',
            'Content-Disposition' => 'attachment; filename="qr-'.preg_replace('/[^A-Za-z0-9._-]/', '_', $asset->code).'.svg"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function labels(Request $request): View
    {
        $ids = collect(explode(',', (string) $request->query('ids')))
            ->map(fn ($id) => (int) $id)->filter()->unique()->take(500)->values();
        $format = array_key_exists((string) $request->query('formato'), self::FORMATS) ? (string) $request->query('formato') : 'piccole';
        $skip = max(0, min((int) $request->query('salta', 0), self::FORMATS[$format]['cols'] * self::FORMATS[$format]['rows'] - 1));

        $assets = Asset::query()->with('location')->whereIn('id', $ids)->orderBy('code')->get();

        return view('assets.labels', [
            'assets' => $assets,
            'format' => $format,
            'formats' => self::FORMATS,
            'spec' => self::FORMATS[$format],
            'skip' => $skip,
            'ids' => $ids->implode(','),
            'company' => (string) settings('company.name', config('app.name')),
            'qr' => $assets->mapWithKeys(fn (Asset $a) => [$a->id => QrCode::svg($a->qrUrl(), quiet: 2)]),
        ]);
    }

    /** Modello per l'importazione, con una riga d'esempio inventata */
    public function template(): Response
    {
        $rows = [
            AssetImport::TEMPLATE_HEADERS,
            ['PRS-01', 'Pressa idraulica 250 t', 'Presse', 'Sito principale > Reparto stampaggio > Linea 1', '', 'SN-000123', 'Costruttore Esempio', 'PI-250', '2018', '15/03/2019', 'A', 'operativa', '125000', '15/03/2024', 'Esempio da cancellare'],
            ['PRS-01-CEN', 'Centralina oleodinamica', 'Centraline', 'Sito principale > Reparto stampaggio > Linea 1', 'PRS-01', '', '', '', '', '', 'B', '', '', '', ''],
        ];
        $out = fopen('php://temp', 'r+');
        fwrite($out, "\xEF\xBB\xBF"); // così Excel riconosce le lettere accentate
        foreach ($rows as $row) {
            fputcsv($out, $row, ';', '"', '');
        }
        rewind($out);
        $csv = (string) stream_get_contents($out);
        fclose($out);

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="manutrack-modello-macchine.csv"',
        ]);
    }
}

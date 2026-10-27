<?php

namespace App\Http\Controllers;

use App\Support\Inventory\PartImport;
use Illuminate\Http\Response;

class InventoryController extends Controller
{
    /** Modello CSV per l'importazione dei ricambi, con due righe d'esempio */
    public function template(): Response
    {
        $rows = [
            PartImport::TEMPLATE_HEADERS,
            ['CUS-6205', 'Cuscinetto a sfere 6205', 'Cuscinetti', 'pz', 'Costruttore Esempio', '6205-2RS', 'Esempio da cancellare', '4', '10', 'Fornitore Esempio', 'F-6205', '7,50', 'Magazzino centrale', '12', '7,20', 'A-03-2'],
            ['OLIO-ISO46', 'Olio idraulico ISO VG 46', 'Lubrificanti', 'l', '', '', '', '20', '60', '', '', '', '', '', '', ''],
        ];
        $out = fopen('php://temp', 'r+');
        fwrite($out, "\xEF\xBB\xBF");
        foreach ($rows as $row) {
            fputcsv($out, $row, ';', '"', '');
        }
        rewind($out);
        $csv = (string) stream_get_contents($out);
        fclose($out);

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="manutrack-modello-ricambi.csv"',
        ]);
    }
}

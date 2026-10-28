<?php

namespace App\Http\Controllers;

use App\Models\DataExport;
use App\Support\Audit\Audit;
use App\Support\Plant\AssetModel;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class GdprController extends Controller
{
    /** Lo ZIP dell'esportazione, solo a chi può esportare e finché non scade */
    public function download(DataExport $export): StreamedResponse
    {
        abort_unless($export->isDownloadable() && Storage::disk(AssetModel::disk())->exists((string) $export->path), 404);
        Audit::log('system.data_export_downloaded', $export);

        return Storage::disk(AssetModel::disk())->download((string) $export->path, basename((string) $export->path), [
            'Content-Type' => 'application/zip',
            'Cache-Control' => 'no-store, private',
        ]);
    }
}

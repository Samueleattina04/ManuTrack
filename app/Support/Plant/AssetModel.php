<?php

namespace App\Support\Plant;

use App\Actions\Assets\StoreAttachment;
use App\Models\Asset;
use App\Support\Audit\Audit;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Modello 3D (GLB) di una macchina, sul disco privato degli allegati.
 * Si accetta solo glTF 2.0 binario: intestazione "glTF", versione 2, lunghezza uguale al file.
 */
final class AssetModel
{
    /** Limite effettivo: quello di ManuTrack, ma non oltre i limiti di caricamento di PHP */
    public static function maxMegabytes(): int
    {
        $limits = [(int) config('manutrack.models.max_mb', 20)];
        foreach (['upload_max_filesize', 'post_max_size'] as $key) {
            $bytes = StoreAttachment::iniBytes((string) ini_get($key));
            if ($bytes > 0) {
                $limits[] = max(1, intdiv($bytes, 1024 * 1024));
            }
        }

        return min($limits);
    }

    public static function maxBytes(): int
    {
        return self::maxMegabytes() * 1024 * 1024;
    }

    public static function disk(): string
    {
        return (string) config('manutrack.attachments.disk', 'local');
    }

    public function store(Asset $asset, UploadedFile $file): void
    {
        $mb = self::maxMegabytes();
        if (! $file->isValid()) {
            throw ValidationException::withMessages(['model' => __('plant.model.invalid')]);
        }
        if ($file->getSize() > self::maxBytes()) {
            throw ValidationException::withMessages(['model' => __('plant.model.too_big', ['mb' => $mb])]);
        }
        if (! self::isGlb((string) $file->getRealPath())) {
            throw ValidationException::withMessages(['model' => __('plant.model.invalid')]);
        }

        $old = $asset->model_path;
        $path = $file->storeAs('models/'.$asset->id, Str::random(32).'.glb', ['disk' => self::disk()]);
        if ($path === false) {
            throw ValidationException::withMessages(['model' => __('plant.model.invalid')]);
        }
        $asset->forceFill([
            'model_path' => $path,
            'model_name' => mb_substr(basename($file->getClientOriginalName()), 0, 150),
            'model_size' => (int) $file->getSize(),
        ])->save();
        if ($old) {
            Storage::disk(self::disk())->delete($old);
        }
        Audit::log('assets.model_uploaded', $asset, $asset->code.' · '.$asset->model_name);
    }

    public function delete(Asset $asset): void
    {
        if ($asset->model_path) {
            Storage::disk(self::disk())->delete($asset->model_path);
        }
        $asset->forceFill(['model_path' => null, 'model_name' => null, 'model_size' => null])->save();
        Audit::log('assets.model_deleted', $asset, $asset->code);
    }

    /** Intestazione GLB: magic "glTF", versione 2, lunghezza totale uguale alla dimensione del file */
    public static function isGlb(string $path): bool
    {
        $handle = @fopen($path, 'rb');
        if (! $handle) {
            return false;
        }
        $header = (string) fread($handle, 20);
        fclose($handle);
        if (strlen($header) < 20 || substr($header, 0, 4) !== 'glTF') {
            return false;
        }
        $values = unpack('Vversion/Vlength/VchunkLength/VchunkType', substr($header, 4));
        if ($values === false) {
            return false;
        }

        // Il primo blocco deve essere il JSON della scena
        return $values['version'] === 2 && $values['length'] === filesize($path) && $values['chunkType'] === 0x4E4F534A;
    }
}

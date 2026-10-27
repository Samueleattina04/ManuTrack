<?php

namespace App\Console\Commands;

use App\Enums\AttachmentKind;
use App\Models\Attachment;
use App\Support\Files\PhotoException;
use App\Support\Files\PhotoProcessor;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Converte le foto già salvate nel formato attuale (WebP 1600 px con miniatura 400 px).
 *
 * Per ogni foto: si crea la versione nuova, si rilegge dal disco per verificarla
 * e solo dopo si aggiorna l'archivio e si cancella l'originale. Se qualcosa va
 * storto l'originale resta com'era.
 */
class OptimizePhotosCommand extends Command
{
    protected $signature = 'manutrack:foto-ottimizza
        {--prova : Non cambia nulla: mostra solo quanto spazio si risparmierebbe}
        {--limite=0 : Al massimo questo numero di foto (0 = tutte)}';

    protected $description = 'Converte in WebP le foto già salvate, con miniatura, senza perdere gli originali in caso di errore';

    public function handle(PhotoProcessor $processor): int
    {
        $format = PhotoProcessor::outputFormat();
        if ($format === 'none') {
            $this->components->error(__('photos.need_driver'));

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('prova');
        $limit = max(0, (int) $this->option('limite'));
        $targetMime = $format === 'webp' ? 'image/webp' : 'image/jpeg';

        // Da convertire: formato diverso da quello attuale, oppure senza miniatura
        $query = Attachment::query()->where('kind', AttachmentKind::Photo->value)
            ->where(fn ($q) => $q->where('mime', '!=', $targetMime)->orWhereNull('variants'))
            ->orderBy('id');
        $total = $limit > 0 ? min($limit, $query->count()) : $query->count();

        if ($total === 0) {
            $this->components->info(__('photos.nothing'));

            return self::SUCCESS;
        }

        $this->components->info(__($dryRun ? 'photos.dry_run' : 'photos.start', ['count' => $total, 'format' => strtoupper($format)]));
        $stats = ['done' => 0, 'failed' => 0, 'before' => 0, 'after' => 0];

        $query->lazyById(50)->when($limit > 0, fn ($photos) => $photos->take($limit))->each(function (Attachment $photo) use ($processor, $dryRun, &$stats): void {
            $disk = Storage::disk($photo->disk);
            $oldFiles = array_values(array_filter([$photo->path, ...array_values($photo->variants ?? [])]));
            $before = array_sum(array_map(fn ($f) => $disk->exists($f) ? (int) $disk->size($f) : 0, $oldFiles));

            $tmp = tempnam(sys_get_temp_dir(), 'mtph');
            try {
                if (! $disk->exists($photo->path)) {
                    throw new PhotoException(__('photos.missing'));
                }
                file_put_contents($tmp, (string) $disk->get($photo->path));
                $result = $processor->process($tmp);
                $after = strlen($result['main']) + strlen((string) $result['thumb']);

                if (! $dryRun) {
                    $this->replace($photo, $result);
                }

                $stats['done']++;
                $stats['before'] += $before;
                $stats['after'] += $after;
                $this->line(sprintf('  #%d %s  %s → %s', $photo->id, $photo->original_name, self::size($before), self::size($after)));
            } catch (Throwable $e) {
                $stats['failed']++;
                $this->components->warn(sprintf('#%d %s: %s', $photo->id, $photo->original_name, $e->getMessage()));
            } finally {
                @unlink($tmp);
            }
        });

        $saved = max(0, $stats['before'] - $stats['after']);
        $this->newLine();
        $this->components->twoColumnDetail(__('photos.converted'), (string) $stats['done']);
        $this->components->twoColumnDetail(__('photos.failed'), (string) $stats['failed']);
        $this->components->twoColumnDetail(__('photos.before'), self::size($stats['before']));
        $this->components->twoColumnDetail(__('photos.after'), self::size($stats['after']));
        $this->components->twoColumnDetail(__($dryRun ? 'photos.would_save' : 'photos.saved'), self::size($saved).($stats['before'] > 0 ? ' ('.round($saved / $stats['before'] * 100).'%)' : ''));

        return $stats['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Scrive i file nuovi, li verifica rileggendoli, aggiorna la riga e solo
     * alla fine cancella i vecchi.
     *
     * @param  array{main: string, mime: string, ext: string, width: int|null, height: int|null, thumb: string|null, thumb_ext: string|null}  $result
     */
    private function replace(Attachment $photo, array $result): void
    {
        $disk = Storage::disk($photo->disk);
        $base = dirname($photo->path).'/'.Str::lower(Str::random(20));
        $main = $base.'.'.$result['ext'];
        $thumb = $result['thumb'] !== null ? $base.'-400.'.$result['thumb_ext'] : null;

        $disk->put($main, $result['main']);
        if ($thumb !== null) {
            $disk->put($thumb, (string) $result['thumb']);
        }

        // Verifica: i file riletti devono essere immagini del formato atteso
        $check = @getimagesizefromstring((string) $disk->get($main));
        $checkThumb = $thumb !== null ? @getimagesizefromstring((string) $disk->get($thumb)) : true;
        if ($check === false || $check['mime'] !== $result['mime'] || $checkThumb === false) {
            $disk->delete(array_filter([$main, $thumb]));
            throw new PhotoException(__('photos.verify_failed'));
        }

        $old = array_values(array_filter([$photo->path, ...array_values($photo->variants ?? [])]));
        $photo->forceFill([
            'path' => $main,
            'mime' => $result['mime'],
            'size' => strlen($result['main']),
            'width' => $result['width'],
            'height' => $result['height'],
            'variants' => $thumb !== null ? ['thumb' => $thumb] : null,
            'original_name' => pathinfo($photo->original_name, PATHINFO_FILENAME).'.'.$result['ext'],
        ])->save();

        $disk->delete(array_values(array_diff($old, [$main, $thumb])));
    }

    private static function size(int $bytes): string
    {
        return $bytes >= 1024 * 1024 ? number_format($bytes / 1024 / 1024, 1, ',', '.').' MB' : number_format($bytes / 1024, 0, ',', '.').' KB';
    }
}

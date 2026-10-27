<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\Attachment;
use App\Models\WorkOrder;
use App\Models\WorkRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * Foto e documenti allegati: si servono solo a chi può vedere l'oggetto a cui
 * appartengono. I file stanno sul disco privato, mai in una cartella pubblica.
 */
class AttachmentController extends Controller
{
    /** Tipi che il browser può mostrare senza rischi (mai HTML o SVG) */
    private const INLINE = ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'];

    public function show(Request $request, Attachment $attachment): Response
    {
        $this->authorizeAttachment($request, $attachment);
        $variant = $request->query('v') === 'thumb' ? 'thumb' : null;

        return $this->respond($attachment, $attachment->pathFor($variant), in_array($attachment->mime, self::INLINE, true));
    }

    public function download(Request $request, Attachment $attachment): Response
    {
        $this->authorizeAttachment($request, $attachment);

        return $this->respond($attachment, $attachment->path, false);
    }

    private function respond(Attachment $attachment, string $path, bool $inline): Response
    {
        $disk = Storage::disk($attachment->disk);
        abort_unless($disk->exists($path), 404);
        // Le miniature hanno lo stesso formato della foto (WebP o JPEG)
        $mime = $path === $attachment->path ? $attachment->mime : (str_ends_with($path, '.webp') ? 'image/webp' : 'image/jpeg');

        $headers = [
            'Content-Type' => $inline ? $mime : 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
            // Privato: niente copie in cache condivise; il browser lo tiene un'ora
            'Cache-Control' => 'private, max-age=3600',
        ];
        // Le immagini non possono eseguire script né caricare altro, nemmeno se manipolate.
        // Ai PDF non si applica "sandbox": Chrome rifiuterebbe di mostrarli.
        if ($inline && $mime !== 'application/pdf') {
            $headers['Content-Security-Policy'] = "default-src 'none'; img-src 'self'; style-src 'unsafe-inline'; sandbox";
        }

        return $disk->response($path, $attachment->original_name, $headers, $inline ? 'inline' : 'attachment');
    }

    /**
     * Macchine: "Vedere le macchine". Segnalazioni e ordini: le stesse regole delle loro schede
     * (chi ha fatto la segnalazione vede le proprie foto).
     */
    private function authorizeAttachment(Request $request, Attachment $attachment): void
    {
        $user = $request->user();
        $owner = $attachment->attachable;

        $allowed = match (true) {
            $owner instanceof Asset => $user?->can('assets.view'),
            $owner instanceof WorkRequest, $owner instanceof WorkOrder => $user?->can('view', $owner),
            default => false,
        };

        abort_unless((bool) $allowed, 403);
    }
}

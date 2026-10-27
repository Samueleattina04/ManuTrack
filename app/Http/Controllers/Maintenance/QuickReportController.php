<?php

namespace App\Http\Controllers\Maintenance;

use App\Actions\Maintenance\SubmitWorkRequest;
use App\Enums\Priority;
use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\Location;
use App\Models\WorkRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Segnalazione rapida: la pagina (che resta disponibile offline) e l'invio.
 *
 * L'invio arriva anche dal service worker (coda offline su Android), che non
 * conosce il token CSRF: la rotta è esclusa dal controllo CSRF ma accetta solo
 * richieste dello stesso sito con l'intestazione X-ManuTrack. Un'intestazione
 * personalizzata non può essere inviata da un altro sito senza un permesso CORS,
 * che ManuTrack non concede; in più il cookie di sessione è SameSite=Lax.
 */
class QuickReportController extends Controller
{
    public function show(Request $request): View
    {
        $user = $request->user();
        $assets = Asset::query()->where('status', '!=', 'dismessa')->orderBy('code')->limit(5000)
            ->get(['id', 'code', 'name', 'location_id', 'qr_token', 'criticality']);
        $labels = Location::labels();

        // Preselezione da QR (?macchina=<codice QR>) o dalla scheda (?id=…)
        $preselect = null;
        if ($token = $request->query('macchina')) {
            $preselect = $assets->firstWhere('qr_token', (string) $token)?->id;
        } elseif ($request->integer('id')) {
            $preselect = $assets->firstWhere('id', $request->integer('id'))?->id;
        }

        $recent = WorkRequest::query()->where('user_id', $user->id)->whereNotNull('asset_id')
            ->latest('id')->limit(20)->pluck('asset_id')->unique()->take(5)->values();

        return view('requests.quick', [
            'config' => [
                'userId' => $user->id,
                'preselect' => $preselect,
                'recent' => $recent,
                'assets' => $assets->map(fn (Asset $a) => [
                    'id' => $a->id,
                    'code' => $a->code,
                    'name' => $a->name,
                    'loc' => $labels[$a->location_id] ?? '',
                    'qr' => $a->qr_token,
                    'a' => $a->criticality->value === 'A',
                ])->values(),
                't' => __('maintenance.quick.js'),
            ],
            'locations' => $labels,
            'priorities' => Priority::cases(),
            'chips' => __('maintenance.quick.chips'),
        ]);
    }

    public function store(Request $request, SubmitWorkRequest $submit): JsonResponse
    {
        abort_unless($request->header('X-ManuTrack') === '1', 400);
        $origin = $request->header('Origin');
        abort_if($origin !== null && rtrim($origin, '/') !== rtrim($request->getSchemeAndHttpHost(), '/'), 403);
        $user = $request->user();

        // Segnalazione scritta da un'altra persona sullo stesso telefono: resta in coda per lei
        if ($request->filled('user_id') && (int) $request->input('user_id') !== $user->id) {
            return response()->json(['message' => __('maintenance.quick.other_user')], 409);
        }

        $input = $request->only(['asset_id', 'location_id', 'description', 'priority', 'client_uuid', 'reported_at']);
        $input['machine_stopped'] = $request->boolean('machine_stopped');
        // La macchina può essere stata eliminata mentre la segnalazione aspettava in coda
        if (! empty($input['asset_id']) && ! Asset::query()->whereKey($input['asset_id'])->exists()) {
            $input['asset_id'] = null;
        }

        $photos = array_values(array_filter((array) $request->file('photos', [])));

        try {
            $result = $submit->handle($user, $input, $photos);
        } catch (ValidationException $e) {
            return response()->json(['message' => $e->getMessage(), 'errors' => $e->errors()], 422);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }

        $wr = $result['request'];

        return response()->json([
            'ok' => true,
            'number' => $wr->number,
            'url' => route('requests.show', $wr),
            'urgent' => $wr->isUrgent(),
            'duplicate' => $result['duplicate'],
            'photo_errors' => $result['photo_errors'],
        ], $result['duplicate'] ? 200 : 201);
    }
}

<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Laravel\Fortify\Contracts\LogoutResponse as LogoutResponseContract;

/**
 * Uscita: oltre alla sessione si svuotano le copie delle pagine salvate dal
 * browser (Segnala offline), così sul telefono condiviso non resta nulla
 * della persona che è uscita. Le segnalazioni non ancora inviate restano:
 * appartengono a chi le ha scritte e partono al suo prossimo accesso.
 */
class LogoutResponse implements LogoutResponseContract
{
    public function toResponse($request): JsonResponse|RedirectResponse
    {
        $response = $request->wantsJson() ? new JsonResponse('', 204) : redirect('/');
        $response->headers->set('Clear-Site-Data', '"cache"');

        return $response;
    }
}

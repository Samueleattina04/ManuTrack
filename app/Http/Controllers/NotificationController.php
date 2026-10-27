<?php

namespace App\Http\Controllers;

use App\Models\UserNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    /** Avvisi non letti, per il contatore sull'icona dell'app (chiamato dal service worker) */
    public function count(Request $request): JsonResponse
    {
        return response()->json(['unread' => UserNotification::unreadCount($request->user())])
            ->header('Cache-Control', 'no-store');
    }
}

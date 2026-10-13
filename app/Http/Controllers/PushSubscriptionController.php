<?php

namespace App\Http\Controllers;

use App\Models\QueueTicket;
use Illuminate\Http\Request;

class PushSubscriptionController extends Controller
{
    public function store(Request $request, QueueTicket $ticket)
    {
        $validated = $request->validate([
            'endpoint' => ['required', 'string', 'max:2048'],
            'keys' => ['required', 'array'],
            'keys.p256dh' => ['required', 'string'],
            'keys.auth' => ['required', 'string'],
            'contentEncoding' => ['nullable', 'string', 'in:aesgcm,aes128gcm'],
        ]);

        $ticket->updatePushSubscription(
            $validated['endpoint'],
            $validated['keys']['p256dh'],
            $validated['keys']['auth'],
            $validated['contentEncoding'] ?? 'aes128gcm'
        );

        return response()->json(['ok' => true]);
    }

    public function destroy(Request $request, QueueTicket $ticket)
    {
        $validated = $request->validate([
            'endpoint' => ['required', 'string', 'max:2048'],
        ]);

        $ticket->deletePushSubscription($validated['endpoint']);

        return response()->json(['ok' => true]);
    }
}

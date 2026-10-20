<?php

namespace App\Http\Controllers;

use App\Models\Restaurant;
use App\Services\QueueEmailNotificationService;
use App\Services\QueueService;
use Illuminate\Http\Request;

class EmailJoinController extends Controller
{
    public function create(Restaurant $restaurant, QueueService $queueService)
    {
        abort_unless($restaurant->active, 404);

        return view('restaurants.join', [
            'restaurant' => $restaurant,
            'snapshot' => $queueService->getRestaurantSnapshot($restaurant),
        ]);
    }

    public function store(Request $request, Restaurant $restaurant, QueueService $queueService, QueueEmailNotificationService $emails)
    {
        abort_unless($restaurant->active, 404);

        $data = $request->validate([
            'party_size' => ['required', 'integer', 'min:1', 'max:'.$restaurant->max_party_size],
            'email' => ['required', 'string', 'email:rfc', 'max:254'],
        ]);

        // A ticket exists only after this explicit submit. Opening the page never creates one.
        $ticket = $queueService->createTicket(
            $restaurant,
            partySize: (int) $data['party_size'],
            email: $data['email'],
        );
        $emails->joined($ticket);

        return redirect()
            ->route('queue.show', ['ticket' => $ticket->public_token])
            ->with('status', 'Antrean dibuat. Konfirmasi sudah kami kirim ke email Anda.');
    }
}

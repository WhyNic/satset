<?php

namespace App\Http\Controllers;

use App\Models\QueueTicket;
use App\Models\Restaurant;
use App\Services\QueueService;
use App\Services\QueuePushNotificationService;
use Illuminate\Http\Request;

class QueueController extends Controller
{
    public function create(Restaurant $restaurant, QueueService $queueService)
    {
        abort_unless($restaurant->active, 404);

        $snapshot = $queueService->getRestaurantSnapshot($restaurant);

        if ($snapshot['estimated_wait'] > $restaurant->max_wait_minutes && ! request()->boolean('force')) {
            return redirect()->route('restaurants.recommendations', $restaurant);
        }

        return redirect()->route('restaurants.show', $restaurant);
    }

    public function store(Request $request, Restaurant $restaurant, QueueService $queueService)
    {
        abort_unless($restaurant->active, 404);

        $ticket = $queueService->createTicket($restaurant);

        return redirect()->route('queue.show', ['ticket' => $ticket->public_token]);
    }

    public function show(QueueTicket $ticket, QueueService $queueService)
    {
        $ticket->load('restaurant');
        $position = $queueService->calculateCustomerPosition($ticket);
        $estimatedWait = $queueService->calculateEstimatedWait($ticket->restaurant, $ticket);

        $pushPublicKey = config('webpush.vapid.public_key');
        $pushSubscriptionCount = $ticket->pushSubscriptions()->count();

        return view('queue.show', compact('ticket', 'position', 'estimatedWait', 'pushPublicKey', 'pushSubscriptionCount'));
    }

    public function status(QueueTicket $ticket, QueueService $queueService)
    {
        $ticket->load('restaurant');
        $position = $queueService->calculateCustomerPosition($ticket);

        return response()->json([
            'status' => $ticket->status,
            'position' => $position,
            'people_before' => max($position - 1, 0),
            'estimated_wait' => $queueService->calculateEstimatedWait($ticket->restaurant, $ticket),
        ]);
    }

    public function cancel(Request $request, QueueTicket $ticket, QueueService $queueService, QueuePushNotificationService $pushNotificationService)
    {
        $queueService->cancelTicket($ticket);
        $pushNotificationService->notifyCancelled($ticket->fresh('restaurant'));

        return redirect()->route('queue.show', ['ticket' => $ticket->public_token])->with('status', 'Antrean berhasil dibatalkan.');
    }
}

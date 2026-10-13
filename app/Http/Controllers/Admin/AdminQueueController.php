<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\QueueTicket;
use App\Models\Restaurant;
use App\Services\QueuePushNotificationService;
use App\Services\QueueService;
use Illuminate\Http\Request;

class AdminQueueController extends Controller
{
    public function show(Restaurant $restaurant, QueueService $queueService)
    {
        $current = $restaurant->queueTickets()->where('status', QueueTicket::STATUS_CALLED)->orderBy('sequence_number')->first();
        $waiting = $restaurant->queueTickets()->where('status', QueueTicket::STATUS_WAITING)->orderBy('sequence_number')->get();
        $activeTickets = $restaurant->queueTickets()
            ->whereIn('status', [QueueTicket::STATUS_CALLED, QueueTicket::STATUS_WAITING])
            ->withCount('pushSubscriptions')
            ->orderBy('sequence_number')
            ->get();
        $snapshot = $queueService->getRestaurantSnapshot($restaurant);

        return view('admin.queue.show', compact('restaurant', 'current', 'waiting', 'activeTickets', 'snapshot'));
    }

    public function callNext(Restaurant $restaurant, QueueService $queueService, QueuePushNotificationService $pushNotificationService)
    {
        $ticket = $queueService->callNext($restaurant);

        if (! $ticket) {
            return back()->with('status', 'Tidak ada antrean waiting atau masih ada customer yang sedang dilayani.');
        }

        $pushNotificationService->notifyCalled($ticket);
        $pushNotificationService->notifyWaitingPositions($restaurant, $queueService);

        return back()->with('status', "Memanggil {$ticket->queue_number}.");
    }

    public function complete(Restaurant $restaurant, QueueService $queueService)
    {
        $ticket = $queueService->completeCurrent($restaurant);

        return back()->with('status', $ticket ? "{$ticket->queue_number} selesai dilayani." : 'Tidak ada customer yang sedang dilayani.');
    }

    public function skip(Restaurant $restaurant, QueueService $queueService)
    {
        $ticket = $queueService->skipCurrent($restaurant);

        return back()->with('status', $ticket ? "{$ticket->queue_number} dilewati." : 'Tidak ada customer yang sedang dilayani.');
    }

    public function cancel(Restaurant $restaurant, QueueTicket $ticket, QueueService $queueService, QueuePushNotificationService $pushNotificationService)
    {
        abort_unless($ticket->restaurant_id === $restaurant->id, 404);

        $queueService->cancelTicket($ticket);
        $pushNotificationService->notifyCancelled($ticket->fresh('restaurant'));

        return back()->with('status', "{$ticket->queue_number} dibatalkan.");
    }

    public function reset(Restaurant $restaurant)
    {
        $restaurant->queueTickets()
            ->whereIn('status', [QueueTicket::STATUS_WAITING, QueueTicket::STATUS_CALLED])
            ->update([
                'status' => QueueTicket::STATUS_CANCELLED,
                'cancelled_at' => now(),
            ]);

        return back()->with('status', 'Antrean aktif berhasil di-reset.');
    }
}

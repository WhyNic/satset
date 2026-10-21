<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\QueueTicket;
use App\Models\Restaurant;
use App\Models\StaffAction;
use App\Services\QueueEmailNotificationService;
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
            ->with('emailEvents')
            ->orderBy('sequence_number')
            ->get();
        $snapshot = $queueService->getRestaurantSnapshot($restaurant);
        $activity = StaffAction::query()
            ->where('restaurant_id', $restaurant->id)
            ->with(['user', 'queueTicket'])
            ->latest('id')
            ->limit(15)
            ->get();

        return view('admin.queue.show', compact('restaurant', 'current', 'waiting', 'activeTickets', 'snapshot', 'activity'));
    }

    public function callNext(Restaurant $restaurant, QueueService $queueService)
    {
        $ticket = $queueService->callNext($restaurant);

        if (! $ticket) {
            return back()->with('status', 'Tidak ada antrean waiting atau masih ada customer yang sedang dilayani.');
        }

        $this->audit($restaurant, 'call-next', $ticket);

        return back()->with('status', "Memanggil {$ticket->queue_number}.");
    }

    public function complete(Restaurant $restaurant, QueueService $queueService)
    {
        $ticket = $queueService->completeCurrent($restaurant);

        if ($ticket) {
            $this->audit($restaurant, 'complete', $ticket);
        }

        return back()->with('status', $ticket ? "{$ticket->queue_number} selesai dilayani." : 'Tidak ada customer yang sedang dilayani.');
    }

    public function skip(Restaurant $restaurant, QueueService $queueService)
    {
        $ticket = $queueService->skipCurrent($restaurant);

        if ($ticket) {
            $this->audit($restaurant, 'skip', $ticket);
        }

        return back()->with('status', $ticket ? "{$ticket->queue_number} dilewati." : 'Tidak ada customer yang sedang dilayani.');
    }

    public function cancel(Restaurant $restaurant, QueueTicket $ticket, QueueService $queueService)
    {
        abort_unless($ticket->restaurant_id === $restaurant->id, 404);

        $queueService->cancelTicket($ticket);
        $this->audit($restaurant, 'cancel', $ticket);

        return back()->with('status', "{$ticket->queue_number} dibatalkan.");
    }

    public function resendEmail(Restaurant $restaurant, QueueTicket $ticket, QueueEmailNotificationService $emails)
    {
        abort_unless($ticket->restaurant_id === $restaurant->id, 404);

        $event = $emails->resend($ticket);
        if ($event === null) {
            return back()->with('status', "Tidak ada email yang perlu dikirim ulang untuk {$ticket->queue_number}.");
        }

        $this->audit($restaurant, 'resend-email', $ticket);

        return back()->with('status', "Email untuk {$ticket->queue_number} dikirim ulang.");
    }

    private function audit(Restaurant $restaurant, string $action, ?QueueTicket $ticket = null): void
    {
        StaffAction::query()->create([
            'user_id' => auth()->id(),
            'restaurant_id' => $restaurant->id,
            'queue_ticket_id' => $ticket?->id,
            'action' => $action,
        ]);
    }
}

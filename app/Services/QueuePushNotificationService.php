<?php

namespace App\Services;

use App\Models\QueueTicket;
use App\Models\Restaurant;
use App\Notifications\QueueAlmostTurnNotification;
use App\Notifications\QueueCalledNotification;
use App\Notifications\QueueCancelledNotification;
use Illuminate\Support\Facades\Log;
use Throwable;

class QueuePushNotificationService
{
    public function notifyCalled(QueueTicket $ticket): void
    {
        $ticket->loadMissing('restaurant');

        if ($ticket->called_notified_at) {
            return;
        }

        if ($this->sendIfSubscribed($ticket, new QueueCalledNotification($ticket))) {
            $ticket->update(['called_notified_at' => now()]);
        }
    }

    public function notifyWaitingPositions(Restaurant $restaurant, QueueService $queueService): void
    {
        $restaurant->queueTickets()
            ->where('status', QueueTicket::STATUS_WAITING)
            ->orderBy('sequence_number')
            ->get()
            ->each(function (QueueTicket $ticket) use ($queueService): void {
                $ticket->load('restaurant');

                $position = $queueService->calculateCustomerPosition($ticket);
                $remaining = max($position - 1, 0);

                if ($remaining === 3 && ! $ticket->three_ahead_notified_at) {
                    if ($this->sendIfSubscribed(
                        $ticket,
                        new QueueAlmostTurnNotification($ticket, 3)
                    )) {
                        $ticket->update([
                            'three_ahead_notified_at' => now(),
                        ]);
                    }
                }

                if ($remaining === 1 && ! $ticket->one_ahead_notified_at) {
                    if ($this->sendIfSubscribed(
                        $ticket,
                        new QueueAlmostTurnNotification($ticket, 1)
                    )) {
                        $ticket->update([
                            'one_ahead_notified_at' => now(),
                            'almost_turn_notified_at' => $ticket->almost_turn_notified_at ?? now(),
                        ]);
                    }
                }
            });
    }

    public function notifyCancelled(QueueTicket $ticket): void
    {
        $ticket->loadMissing('restaurant');

        $this->sendIfSubscribed(
            $ticket,
            new QueueCancelledNotification($ticket)
        );
    }

    private function sendIfSubscribed(
        QueueTicket $ticket,
        object $notification
    ): bool {
        if (
            ! config('webpush.vapid.public_key') ||
            ! config('webpush.vapid.private_key')
        ) {
            return false;
        }

        if (! $ticket->pushSubscriptions()->exists()) {
            return false;
        }

        try {
            $ticket->notify($notification);

            return true;
        } catch (Throwable $e) {
            Log::error('Web Push notification failed', [
                'ticket_id' => $ticket->id,
                'queue_number' => $ticket->queue_number,
                'notification' => $notification::class,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
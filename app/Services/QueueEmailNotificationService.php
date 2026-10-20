<?php

namespace App\Services;

use App\Jobs\SendQueueEmailJob;
use App\Models\QueueTicket;
use App\Models\Restaurant;
use App\Models\TicketEmailEvent;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Decides which queue events send an email. Sending itself runs in SendQueueEmailJob.
 * Scheduling failures are logged and never propagate, so a queue action always succeeds.
 */
class QueueEmailNotificationService
{
    public function joined(QueueTicket $ticket): void
    {
        $this->dispatchFor($ticket, TicketEmailEvent::JOINED);
    }

    public function afterChange(Restaurant $restaurant, QueueService $queue, ?QueueTicket $ticket, ?string $event): void
    {
        try {
            if ($ticket && $event === 'called') {
                $this->dispatchFor($ticket, TicketEmailEvent::CALLED);
            }
            if ($ticket && $event === 'cancelled') {
                $this->dispatchFor($ticket, TicketEmailEvent::CANCELLED);
            }

            $waiting = $restaurant->queueTickets()
                ->where('status', QueueTicket::STATUS_WAITING)
                ->whereNotNull('email')
                ->orderBy('sequence_number')
                ->get();

            foreach ($waiting as $candidate) {
                $remaining = max(0, $queue->calculateCustomerPosition($candidate) - 1);

                if ($remaining === 3) {
                    $this->dispatchFor($candidate, TicketEmailEvent::THREE_AHEAD);
                }
                if ($remaining === 1) {
                    $this->dispatchFor($candidate, TicketEmailEvent::ONE_AHEAD);
                }
            }
        } catch (Throwable $exception) {
            Log::error('Queue email scheduling failed', ['restaurant_id' => $restaurant->id, 'error' => $exception::class]);
        }
    }

    private function dispatchFor(QueueTicket $ticket, string $event): void
    {
        if (! config('email_alerts.enabled') || blank($ticket->email)) {
            return;
        }

        try {
            SendQueueEmailJob::dispatch($ticket->id, $event, $ticket->email);
        } catch (Throwable $exception) {
            Log::error('Queue email dispatch failed', ['ticket_id' => $ticket->id, 'event' => $event, 'error' => $exception::class]);
        }
    }
}

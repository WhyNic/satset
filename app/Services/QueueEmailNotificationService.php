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
 *
 * Threshold rule, applied to every waiting ticket with an email whenever the queue changes or a
 * customer joins:
 *   - 2 or 3 people ahead: "three ahead" (sent once; the text shows the real count)
 *   - exactly 1 person ahead: "one ahead" (sent once)
 * Being within 3 at the moment of joining counts, so nobody who joins close to the front is skipped.
 *
 * Scheduling failures are logged and never propagate, so a queue action or a join always succeeds.
 */
class QueueEmailNotificationService
{
    public function __construct(private QueueService $queue) {}

    public function joined(QueueTicket $ticket): void
    {
        $this->dispatchFor($ticket, TicketEmailEvent::JOINED);

        try {
            $this->thresholdFor($ticket, $this->queue);
        } catch (Throwable $exception) {
            Log::error('Queue threshold check on join failed', ['ticket_id' => $ticket->id, 'error' => $exception::class]);
        }
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
                $this->thresholdFor($candidate, $queue);
            }
        } catch (Throwable $exception) {
            Log::error('Queue email scheduling failed', ['restaurant_id' => $restaurant->id, 'error' => $exception::class]);
        }
    }

    /**
     * Active tickets ahead of this one, counting a called ticket as ahead until staff complete or skip it.
     */
    public static function peopleAhead(QueueTicket $ticket, QueueService $queue): int
    {
        return max(0, $queue->calculateCustomerPosition($ticket) - 1);
    }

    private function thresholdFor(QueueTicket $ticket, QueueService $queue): void
    {
        if ($ticket->status !== QueueTicket::STATUS_WAITING || blank($ticket->email)) {
            return;
        }

        $ahead = self::peopleAhead($ticket, $queue);

        if ($ahead === 1) {
            $this->dispatchFor($ticket, TicketEmailEvent::ONE_AHEAD);
        } elseif ($ahead >= 2 && $ahead <= 3) {
            $this->dispatchFor($ticket, TicketEmailEvent::THREE_AHEAD);
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

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
 * Who may receive what:
 *   - The confirmation (joined) goes to any email on a new ticket. It carries the verification link.
 *   - Every other alert waits until the owner of the email has verified it. Verification later
 *     re-runs the checks, so a late verifier still gets the alert that applies to their current place.
 *
 * Threshold rule, checked on every queue change, on every join, and on verification:
 *   - 2 or 3 people ahead: "three ahead" (sent once; the text shows the real count)
 *   - exactly 1 person ahead: "one ahead" (sent once)
 *
 * Scheduling failures are logged and never propagate, so a queue action or a join always succeeds.
 */
class QueueEmailNotificationService
{
    public function __construct(private QueueService $queue) {}

    public function joined(QueueTicket $ticket): void
    {
        $this->dispatchFor($ticket, TicketEmailEvent::JOINED);
        $this->safely(fn () => $this->thresholdFor($ticket, $this->queue), $ticket->id);
    }

    /**
     * The owner of the email has clicked the link. Send whatever applies to their place in the queue right now.
     */
    public function verified(QueueTicket $ticket): void
    {
        $fresh = $ticket->fresh();
        if (! $fresh || ! $fresh->isEmailVerified()) {
            return;
        }

        $this->safely(function () use ($fresh): void {
            if ($fresh->status === QueueTicket::STATUS_CALLED) {
                $this->dispatchFor($fresh, TicketEmailEvent::CALLED);
            }
            $this->thresholdFor($fresh, $this->queue);
        }, $ticket->id);
    }

    /**
     * The customer asked for a new verification link. Only meaningful while the email is still unverified.
     */
    public function resendVerification(QueueTicket $ticket): bool
    {
        if (! $ticket->isActive() || blank($ticket->email) || $ticket->isEmailVerified()) {
            return false;
        }

        $this->clearClaim($ticket, TicketEmailEvent::JOINED);
        $this->dispatchFor($ticket, TicketEmailEvent::JOINED);

        return true;
    }

    /**
     * Staff asked to send the customer the email that matches their current state again.
     * Returns the event that was sent again, or null when nothing applies.
     */
    public function resend(QueueTicket $ticket): ?string
    {
        if (blank($ticket->email)) {
            return null;
        }

        if (! $ticket->isEmailVerified()) {
            return $this->resendVerification($ticket) ? TicketEmailEvent::JOINED : null;
        }

        if ($ticket->status === QueueTicket::STATUS_CALLED) {
            $this->clearClaim($ticket, TicketEmailEvent::CALLED);
            $this->dispatchFor($ticket, TicketEmailEvent::CALLED);

            return TicketEmailEvent::CALLED;
        }

        if ($ticket->status !== QueueTicket::STATUS_WAITING) {
            return null;
        }

        $event = match (self::peopleAhead($ticket, $this->queue)) {
            1 => TicketEmailEvent::ONE_AHEAD,
            2, 3 => TicketEmailEvent::THREE_AHEAD,
            default => null,
        };
        if ($event === null) {
            return null;
        }

        $this->clearClaim($ticket, $event);
        $this->dispatchFor($ticket, $event);

        return $event;
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

    private function clearClaim(QueueTicket $ticket, string $event): void
    {
        TicketEmailEvent::query()->where('queue_ticket_id', $ticket->id)->where('event', $event)->delete();
    }

    private function dispatchFor(QueueTicket $ticket, string $event): void
    {
        if (! config('email_alerts.enabled') || blank($ticket->email)) {
            return;
        }

        if ($event !== TicketEmailEvent::JOINED && ! $ticket->isEmailVerified()) {
            return;
        }

        try {
            SendQueueEmailJob::dispatch($ticket->id, $event, $ticket->email);
        } catch (Throwable $exception) {
            Log::error('Queue email dispatch failed', ['ticket_id' => $ticket->id, 'event' => $event, 'error' => $exception::class]);
        }
    }

    private function safely(\Closure $callback, int $ticketId): void
    {
        try {
            $callback();
        } catch (Throwable $exception) {
            Log::error('Queue email check failed', ['ticket_id' => $ticketId, 'error' => $exception::class]);
        }
    }
}

<?php

namespace App\Jobs;

use App\Mail\QueueEmail;
use App\Models\QueueTicket;
use App\Models\TicketEmailEvent;
use App\Services\QueueEmailNotificationService;
use App\Services\QueueService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendQueueEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private const STALE_AFTER_MINUTES = 10;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [30, 120, 600];

    // The address is captured at dispatch time: closed tickets lose their email before a queued job may run.
    public function __construct(public int $ticketId, public string $event, public string $email) {}

    public function handle(): void
    {
        if (! config('email_alerts.enabled')) {
            return;
        }

        $claim = $this->claim();
        if (! $claim) {
            return;
        }

        try {
            $ticket = QueueTicket::query()->with('restaurant')->find($this->ticketId);
            if (! $ticket) {
                $claim->delete();

                return;
            }

            if (! $this->stillRelevant($ticket)) {
                // Settle the event without sending, so an outdated alert is never delivered or retried.
                $claim->update(['sent_at' => now()]);

                return;
            }

            Mail::to($this->email)->send(new QueueEmail($ticket, $this->event));
            $claim->update(['sent_at' => now()]);
        } catch (Throwable $exception) {
            // Release the claim so the retry can send. The address is never logged.
            $claim->delete();
            Log::warning('Queue email not sent, will retry', [
                'ticket_id' => $this->ticketId,
                'event' => $this->event,
                'error' => $exception::class,
            ]);

            throw $exception;
        }
    }

    /**
     * A "three ahead" or "one ahead" alert is outdated once the customer is no longer waiting, and a
     * "called" alert once they are no longer called. Joined and cancelled notices always stay relevant.
     */
    private function stillRelevant(QueueTicket $ticket): bool
    {
        return match ($this->event) {
            TicketEmailEvent::JOINED, TicketEmailEvent::CANCELLED => true,
            TicketEmailEvent::CALLED => $ticket->status === QueueTicket::STATUS_CALLED,
            // The threshold is checked again at send time: an alert whose count has already moved on is dropped.
            TicketEmailEvent::THREE_AHEAD => $ticket->status === QueueTicket::STATUS_WAITING
                && in_array($this->peopleAheadNow($ticket), [2, 3], true),
            TicketEmailEvent::ONE_AHEAD => $ticket->status === QueueTicket::STATUS_WAITING
                && $this->peopleAheadNow($ticket) === 1,
        };
    }

    private function peopleAheadNow(QueueTicket $ticket): int
    {
        return QueueEmailNotificationService::peopleAhead($ticket, app(QueueService::class));
    }

    /**
     * Returns the claim when this run may send, or null when the event was already settled or is in flight.
     * A claim left unsent for longer than the stale window (crashed worker) is reclaimed atomically, so only
     * one worker can take it over.
     */
    private function claim(): ?TicketEmailEvent
    {
        $key = ['queue_ticket_id' => $this->ticketId, 'event' => $this->event];
        $claim = TicketEmailEvent::query()->firstOrCreate($key);

        if ($claim->wasRecentlyCreated) {
            return $claim;
        }

        $reclaimed = TicketEmailEvent::query()
            ->whereKey($claim->id)
            ->whereNull('sent_at')
            ->where('created_at', '<', now()->subMinutes(self::STALE_AFTER_MINUTES))
            ->update(['created_at' => now()]);

        return $reclaimed === 1 ? $claim->fresh() : null;
    }
}

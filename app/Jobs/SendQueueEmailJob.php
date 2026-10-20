<?php

namespace App\Jobs;

use App\Mail\QueueEmail;
use App\Models\QueueTicket;
use App\Models\TicketEmailEvent;
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
     * Returns the claim when this run may send, or null when the event was already sent or is in flight.
     * A claim left unsent for longer than the stale window (crashed worker) is reclaimed.
     */
    private function claim(): ?TicketEmailEvent
    {
        $key = ['queue_ticket_id' => $this->ticketId, 'event' => $this->event];
        $claim = TicketEmailEvent::query()->firstOrCreate($key);

        if ($claim->wasRecentlyCreated) {
            return $claim;
        }

        $stale = $claim->sent_at === null && $claim->created_at->lt(now()->subMinutes(10));
        if (! $stale) {
            return null;
        }

        $claim->delete();

        return TicketEmailEvent::query()->create($key);
    }
}

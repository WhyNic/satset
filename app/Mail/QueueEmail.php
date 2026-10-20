<?php

namespace App\Mail;

use App\Models\QueueTicket;
use App\Models\TicketEmailEvent;
use App\Services\QueueService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class QueueEmail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public QueueTicket $ticket, public string $event) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->subjectLine());
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.queue',
            text: 'emails.queue-text',
            with: [
                'heading' => $this->subjectLine(),
                'lines' => $this->lines(),
                'url' => route('queue.show', ['ticket' => $this->ticket->public_token]),
            ],
        );
    }

    private function subjectLine(): string
    {
        $number = $this->ticket->queue_number;
        $name = $this->ticket->restaurant->name;

        return match ($this->event) {
            TicketEmailEvent::JOINED => "Antrean {$number} di {$name} sudah dibuat",
            TicketEmailEvent::THREE_AHEAD => "Tinggal 3 antrean lagi, {$number}",
            TicketEmailEvent::ONE_AHEAD => "Tinggal 1 antrean lagi, {$number}",
            TicketEmailEvent::CALLED => "Giliran {$number} sudah tiba",
            TicketEmailEvent::CANCELLED => "Antrean {$number} dibatalkan",
        };
    }

    /**
     * @return list<string>
     */
    private function lines(): array
    {
        $number = $this->ticket->queue_number;
        $name = $this->ticket->restaurant->name;

        return match ($this->event) {
            TicketEmailEvent::JOINED => $this->joinedLines($number, $name),
            TicketEmailEvent::THREE_AHEAD => [
                "Tinggal 3 antrean lagi sebelum giliran {$number} di {$name}.",
                "Silakan bersiap menuju {$name}.",
            ],
            TicketEmailEvent::ONE_AHEAD => [
                "Tinggal 1 antrean lagi sebelum {$number}.",
                "Silakan bersiap menuju {$name}.",
            ],
            TicketEmailEvent::CALLED => [
                "Giliran {$number} sudah tiba!",
                "Silakan menuju {$name}.",
            ],
            TicketEmailEvent::CANCELLED => [
                "Antrean {$number} di {$name} telah dibatalkan.",
            ],
        };
    }

    /**
     * @return list<string>
     */
    private function joinedLines(string $number, string $name): array
    {
        $queue = app(QueueService::class);
        $before = max($queue->calculateCustomerPosition($this->ticket) - 1, 0);
        $estimate = $queue->calculateEstimatedWait($this->ticket->restaurant, $this->ticket);

        return [
            "Antrean Anda di {$name} sudah dibuat.",
            "Nomor antrean: {$number}",
            "Jumlah tamu: {$this->ticket->party_size} orang",
            "Antrean di depan Anda: {$before}",
            "Estimasi waktu tunggu: +/- {$estimate} menit",
        ];
    }
}

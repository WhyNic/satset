<?php

namespace App\Mail;

use App\Models\QueueTicket;
use App\Models\TicketEmailEvent;
use App\Services\QueueEmailNotificationService;
use App\Services\QueueService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class QueueEmail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public QueueTicket $ticket, public string $event, public ?string $verifyToken = null) {}

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
                'verifyUrl' => $this->verifyUrl(),
            ],
        );
    }

    private function subjectLine(): string
    {
        $number = $this->ticket->queue_number;
        $name = $this->ticket->restaurant->name;

        return match ($this->event) {
            TicketEmailEvent::JOINED => "Antrean {$number} di {$name} sudah dibuat",
            TicketEmailEvent::THREE_AHEAD => "Tinggal {$this->peopleAhead()} antrean lagi, {$number}",
            TicketEmailEvent::ONE_AHEAD => "Tinggal 1 antrean lagi, {$number}",
            TicketEmailEvent::CALLED => $this->ticket->restaurant->isPickup() ? "Pesanan {$number} siap diambil" : "Giliran {$number} sudah tiba",
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
                "Tinggal {$this->peopleAhead()} antrean lagi sebelum giliran {$number} di {$name}.",
                $this->ticket->restaurant->isPickup() ? "Silakan bersiap di {$name}." : "Silakan bersiap menuju {$name}.",
            ],
            TicketEmailEvent::ONE_AHEAD => [
                "Tinggal 1 antrean lagi sebelum {$number}.",
                $this->ticket->restaurant->isPickup() ? "Silakan bersiap di {$name}." : "Silakan bersiap menuju {$name}.",
            ],
            TicketEmailEvent::CALLED => $this->ticket->restaurant->isPickup()
                ? ["Pesanan {$number} siap!", "Silakan ambil di {$name}."]
                : ["Giliran {$number} sudah tiba!", "Silakan menuju {$name}."],
            TicketEmailEvent::CANCELLED => [
                "Antrean {$number} di {$name} telah dibatalkan.",
            ],
        };
    }

    /**
     * Only the confirmation carries the verification link, and only while a token has just been issued.
     */
    private function verifyUrl(): ?string
    {
        if ($this->event !== TicketEmailEvent::JOINED || $this->verifyToken === null) {
            return null;
        }

        return route('queue.verify', ['ticket' => $this->ticket->public_token, 'token' => $this->verifyToken]);
    }

    /**
     * The count as it stands when the email is rendered, so "tinggal N" never disagrees with the queue.
     */
    private function peopleAhead(): int
    {
        return QueueEmailNotificationService::peopleAhead($this->ticket, app(QueueService::class));
    }

    /**
     * @return list<string>
     */
    private function joinedLines(string $number, string $name): array
    {
        $queue = app(QueueService::class);
        $before = max($queue->calculateCustomerPosition($this->ticket) - 1, 0);
        $estimate = $queue->calculateEstimatedWait($this->ticket->restaurant, $this->ticket);

        return array_values(array_filter([
            "Antrean Anda di {$name} sudah dibuat.",
            "Nomor antrean: {$number}",
            // Pickup outlets have no seats, so the party size is not part of the message.
            $this->ticket->restaurant->isPickup() ? null : "Jumlah tamu: {$this->ticket->party_size} orang",
            "Antrean di depan Anda: {$before}",
            "Estimasi waktu tunggu: +/- {$estimate} menit",
        ]));
    }
}

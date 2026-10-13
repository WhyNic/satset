<?php

namespace App\Notifications;

use App\Models\QueueTicket;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

class QueueAlmostTurnNotification extends Notification
{
    public function __construct(
        private readonly QueueTicket $ticket,
        private readonly int $remaining
    ) {
    }

    public function via(object $notifiable): array
    {
        return [WebPushChannel::class];
    }

    public function toWebPush(object $notifiable, mixed $notification): WebPushMessage
    {
        $body = $this->remaining <= 1
            ? 'Giliran Anda sebentar lagi. Silakan bersiap menuju restoran.'
            : "Tinggal {$this->remaining} antrean lagi sebelum giliran Anda di {$this->ticket->restaurant->name}.";

        return (new WebPushMessage)
            ->title('SatSet')
            ->body($body)
            ->icon('/icons/icon-192.png')
            ->badge('/icons/badge-72.png')
            ->tag("satset-queue-{$this->ticket->public_token}-almost-{$this->remaining}")
            ->data(['url' => route('queue.show', ['ticket' => $this->ticket->public_token])]);
    }
}

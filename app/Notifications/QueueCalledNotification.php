<?php

namespace App\Notifications;

use App\Models\QueueTicket;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

class QueueCalledNotification extends Notification
{
    public function __construct(private readonly QueueTicket $ticket)
    {
    }

    public function via(object $notifiable): array
    {
        return [WebPushChannel::class];
    }

    public function toWebPush(object $notifiable, mixed $notification): WebPushMessage
    {
        return (new WebPushMessage)
            ->title('SatSet')
            ->body("Giliran {$this->ticket->queue_number} sudah tiba! Silakan menuju {$this->ticket->restaurant->name}.")
            ->icon('/icons/icon-192.png')
            ->badge('/icons/badge-72.png')
            ->tag("satset-queue-{$this->ticket->public_token}-called")
            ->requireInteraction()
            ->data(['url' => route('queue.show', ['ticket' => $this->ticket->public_token])]);
    }
}

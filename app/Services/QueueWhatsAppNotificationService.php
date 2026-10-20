<?php

namespace App\Services;

use App\Models\QueueTicket;
use App\Models\Restaurant;
use App\Models\WhatsAppConversation;

class QueueWhatsAppNotificationService
{
    public function __construct(private WhatsAppService $whatsapp) {}

    public function notifyCalled(QueueTicket $ticket): void
    {
        if (! $ticket->called_notified_at) {
            $this->notify($ticket, 'called', "Giliran {$ticket->queue_number} sudah tiba!\nSilakan menuju {$ticket->restaurant->name}.", 'called_notified_at');
        }
    }

    public function notifyCancelled(QueueTicket $ticket): void
    {
        $this->notify($ticket, 'cancelled', "Antrean {$ticket->queue_number} di {$ticket->restaurant->name} telah dibatalkan.");
    }

    public function notifyWaitingPositions(Restaurant $restaurant, QueueService $queue): void
    {
        foreach ($restaurant->queueTickets()->where('status', QueueTicket::STATUS_WAITING)->orderBy('sequence_number')->get() as $ticket) {
            $remaining = max(0, $queue->calculateCustomerPosition($ticket) - 1);
            if ($remaining === 3 && ! $ticket->three_ahead_notified_at) {
                $this->notify($ticket, 'three_ahead', "Tinggal 3 antrean lagi sebelum giliran {$ticket->queue_number} di {$restaurant->name}.", 'three_ahead_notified_at');
            }
            if ($remaining === 1 && ! $ticket->one_ahead_notified_at) {
                $this->notify($ticket, 'one_ahead', "Tinggal 1 antrean lagi sebelum {$ticket->queue_number}.\nSilakan bersiap menuju {$restaurant->name}.", 'one_ahead_notified_at');
            }
        }
    }

    private function notify(QueueTicket $ticket, string $event, string $text, ?string $field = null): void
    {
        if (! $ticket->phone_number) {
            return;
        }

        $key = "ticket:{$ticket->id}:{$event}";
        $template = config("whatsapp.templates.{$event}");
        $recent = WhatsAppConversation::query()->where('phone_number', $ticket->phone_number)
            ->where('last_message_at', '>', now()->subHours(24))->exists();

        if (! $recent && filled($template)) {
            $this->whatsapp->sendTemplateMessage($ticket->phone_number, $template,
                [$ticket->queue_number, $ticket->restaurant->name], $text, $ticket, eventKey: $key, notificationField: $field);
        } else {
            $this->whatsapp->sendTextMessage($ticket->phone_number, $text, $ticket, eventKey: $key, notificationField: $field);
        }
    }
}

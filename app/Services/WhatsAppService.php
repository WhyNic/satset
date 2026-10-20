<?php

namespace App\Services;

use App\Models\QueueTicket;
use App\Models\Restaurant;
use App\Models\WhatsappLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class WhatsAppService
{
    public function ready(): bool
    {
        return config('whatsapp.enabled')
            && filled(config('whatsapp.access_token'))
            && preg_match('/^\d+$/', (string) config('whatsapp.phone_number_id'))
            && preg_match('/^v\d+\.\d+$/', (string) config('whatsapp.api_version'));
    }

    public function entryUrl(Restaurant $restaurant): ?string
    {
        $phone = app(QueueService::class)->normalizePhoneNumber((string) config('whatsapp.phone_number'));

        if (! $this->ready() || ! preg_match('/^[1-9]\d{7,14}$/', $phone)) {
            return null;
        }

        return 'https://wa.me/'.$phone.'?'.http_build_query(
            ['text' => 'ANTRE '.$restaurant->slug], '', '&', PHP_QUERY_RFC3986
        );
    }

    public function sendTextMessage(string $phone, string $text, ?QueueTicket $ticket = null, ?Restaurant $restaurant = null, ?string $eventKey = null, ?string $notificationField = null): WhatsappLog
    {
        return $this->enqueue($phone, $text, [
            'type' => 'text',
            'text' => ['preview_url' => false, 'body' => $text],
        ], $ticket, $restaurant, $eventKey, $notificationField);
    }

    public function sendTemplateMessage(string $phone, string $name, array $parameters, string $summary, ?QueueTicket $ticket = null, ?Restaurant $restaurant = null, ?string $eventKey = null, ?string $notificationField = null): WhatsappLog
    {
        return $this->enqueue($phone, $summary, [
            'type' => 'template',
            'template' => [
                'name' => $name,
                'language' => ['code' => config('whatsapp.template_language', 'id')],
                'components' => [[
                    'type' => 'body',
                    'parameters' => array_map(fn ($value): array => ['type' => 'text', 'text' => (string) $value], $parameters),
                ]],
            ],
        ], $ticket, $restaurant, $eventKey, $notificationField);
    }

    private function enqueue(string $phone, string $summary, array $payload, ?QueueTicket $ticket, ?Restaurant $restaurant, ?string $eventKey, ?string $notificationField): WhatsappLog
    {
        $values = [
            'phone_number' => app(QueueService::class)->normalizePhoneNumber($phone),
            'restaurant_id' => $restaurant?->id ?? $ticket?->restaurant_id,
            'queue_ticket_id' => $ticket?->id,
            'direction' => 'outbound',
            'message' => $summary,
            'payload' => $payload,
            'status' => 'pending',
            'notification_field' => $notificationField,
        ];

        return $eventKey
            ? WhatsappLog::query()->firstOrCreate(['event_key' => $eventKey], $values)
            : WhatsappLog::query()->create($values);
    }

    public function deliver(WhatsappLog $message): bool
    {
        // Claim once before HTTP. Ambiguous transport failures require manual review.
        $claimed = WhatsappLog::query()->whereKey($message->id)->where('status', 'pending')
            ->update(['status' => 'sending', 'attempts' => DB::raw('attempts + 1'), 'updated_at' => now()]);
        if (! $claimed) {
            return false;
        }

        try {
            if (! $this->ready()) {
                return $this->failed($message, 'not_configured');
            }

            $payload = $message->payload;
            if (($payload['type'] ?? null) === 'text') {
                $recentContact = \App\Models\WhatsAppConversation::query()
                    ->where('phone_number', $message->phone_number)
                    ->where('last_message_at', '>', now()->subHours(24))->exists();
                if (! $recentContact) {
                    return $this->failed($message, 'template_required');
                }
            }

            $response = Http::withToken(config('whatsapp.access_token'))
                ->acceptJson()->connectTimeout(3)->timeout(8)
                ->post('https://graph.facebook.com/'.config('whatsapp.api_version').'/'.config('whatsapp.phone_number_id').'/messages', [
                    'messaging_product' => 'whatsapp',
                    'recipient_type' => 'individual',
                    'to' => $message->phone_number,
                    ...$payload,
                ]);

            $providerId = $response->json('messages.0.id');
            if (! $response->successful() || ! is_string($providerId) || $providerId === '') {
                return $this->failed($message, 'http_'.$response->status());
            }

            DB::transaction(function () use ($message, $providerId): void {
                $message->update(['status' => 'sent', 'provider_message_id' => $providerId, 'sent_at' => now(), 'error_code' => null]);
                if ($message->queue_ticket_id && in_array($message->notification_field, [
                    'three_ahead_notified_at', 'one_ahead_notified_at', 'called_notified_at',
                ], true)) {
                    QueueTicket::query()->whereKey($message->queue_ticket_id)
                        ->whereNull($message->notification_field)->update([$message->notification_field => now()]);
                }
            });

            return true;
        } catch (Throwable) {
            return $this->failed($message, 'transport_or_delivery_error');
        }
    }

    private function failed(WhatsappLog $message, string $code): bool
    {
        $message->update(['status' => 'failed', 'error_code' => $code]);
        Log::warning('WhatsApp delivery failed', ['log_id' => $message->id, 'code' => $code]);

        return false;
    }

    public function dispatchPending(): void
    {
        try {
            WhatsappLog::query()->where('direction', 'outbound')->where('status', 'pending')
                ->orderBy('id')->limit(100)->pluck('id')
                ->each(fn (int $id) => \App\Jobs\DeliverWhatsAppMessage::dispatch($id)->afterCommit());
        } catch (Throwable) {
            Log::warning('WhatsApp outbox dispatch deferred; run whatsapp:send-pending.');
        }
    }
}

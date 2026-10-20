<?php

namespace App\Services;

use App\Models\QueueTicket;
use App\Models\Restaurant;
use App\Models\WhatsAppConversation;
use App\Models\WhatsappLog;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WhatsAppChatbotService
{
    public function __construct(private QueueService $queue, private WhatsAppService $whatsapp) {}

    public function receive(array $message): void
    {
        $id = $message['id'] ?? null;
        $phone = $this->queue->normalizePhoneNumber((string) ($message['from'] ?? ''));
        if (! is_string($id) || strlen($id) > 255 || $id === '' || ! preg_match('/^[1-9]\d{7,14}$/', $phone)) {
            return;
        }
        $text = (string) ($message['text']['body'] ?? $message['interactive']['button_reply']['id']
            ?? $message['interactive']['list_reply']['id'] ?? $message['button']['payload'] ?? '');
        $text = mb_substr(trim($text), 0, 1000);
        $receivedAt = isset($message['timestamp']) && ctype_digit((string) $message['timestamp'])
            ? Carbon::createFromTimestamp((int) $message['timestamp'])
            : now();
        if ($receivedAt->isFuture()) {
            $receivedAt = now();
        }

        DB::transaction(function () use ($id, $phone, $text, $receivedAt): void {
            if (! DB::table('whatsapp_incoming_messages')->insertOrIgnore([
                'provider_message_id' => $id, 'phone_number' => $phone,
                'created_at' => now(), 'updated_at' => now(),
            ])) {
                return;
            }
            DB::table('whatsapp_conversations')->insertOrIgnore([
                'phone_number' => $phone, 'state' => 'restaurant_selected',
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $conversation = WhatsAppConversation::query()->where('phone_number', $phone)->lockForUpdate()->firstOrFail();
            WhatsappLog::query()->create([
                'direction' => 'inbound', 'status' => 'received', 'message' => $text,
                'provider_message_id' => $id, 'phone_number' => $phone,
                'restaurant_id' => $conversation->restaurant_id,
            ]);
            if ($conversation->last_message_at && $receivedAt->lt($conversation->last_message_at)) {
                return;
            }
            $conversation->last_message_at = $receivedAt;
            $reply = $this->advance($conversation, $text);
            $conversation->save();
            $ticket = $conversation->queue_ticket_id ? QueueTicket::query()->find($conversation->queue_ticket_id) : null;
            $restaurant = $conversation->restaurant_id ? Restaurant::query()->find($conversation->restaurant_id) : null;
            WhatsappLog::query()->where('direction', 'inbound')->where('provider_message_id', $id)
                ->update(['restaurant_id' => $restaurant?->id, 'queue_ticket_id' => $ticket?->id]);
            $this->whatsapp->sendTextMessage($phone, $reply, $ticket, $restaurant, 'reply:'.$id);
        }, 3);
    }

    private function advance(WhatsAppConversation $conversation, string $text): string
    {
        if (preg_match('/^ANTRE\s+([a-z0-9-]+)$/i', $text, $matches)) {
            $restaurant = Restaurant::query()->where('slug', $matches[1])->lockForUpdate()->first();
            if (! $restaurant) {
                $conversation->fill(['restaurant_id' => null, 'queue_ticket_id' => null, 'party_size' => null, 'state' => 'restaurant_selected', 'expires_at' => null]);

                return 'Outlet tidak ditemukan. Silakan scan ulang QR SatSet di lokasi.';
            }
            $conversation->fill(['restaurant_id' => $restaurant->id, 'party_size' => null, 'queue_ticket_id' => null,
                'state' => 'waiting_party_size', 'expires_at' => now()->addMinutes(30)]);
            if (! $restaurant->active || ! $restaurant->queue_open) {
                $conversation->state = 'restaurant_selected';

                return "Antrean {$restaurant->name} sedang ditutup. Silakan hubungi staf outlet.";
            }
            if ($ticket = $this->queue->findActiveTicketForPhone($restaurant, $conversation->phone_number)) {
                $conversation->fill(['state' => 'queue_created', 'queue_ticket_id' => $ticket->id]);

                return $this->ticketSummary($ticket, 'Anda sudah memiliki antrean aktif.');
            }
            $snapshot = $this->queue->getRestaurantSnapshot($restaurant);

            return "Halo! Anda sedang mendaftar antrean di {$restaurant->name}.\n\nSaat ini ada {$snapshot['active_count']} antrean di depan Anda. Estimasi +/-{$snapshot['estimated_wait']} menit.\n\nBerapa orang yang akan datang? Balas angka 1 sampai {$restaurant->max_party_size}.\nSatu kelompok mendapat satu nomor antrean. Update antrean akan dikirim ke WhatsApp ini.";
        }

        if (! $conversation->restaurant_id || ! $conversation->expires_at || $conversation->expires_at->isPast()) {
            $conversation->state = 'restaurant_selected';

            return 'Untuk memulai atau melanjutkan pendaftaran, scan QR outlet dan pilih Lanjut via WhatsApp.';
        }
        $restaurant = Restaurant::query()->lockForUpdate()->find($conversation->restaurant_id);
        if (! $restaurant) {
            return 'Outlet tidak tersedia. Silakan scan QR di lokasi.';
        }
        if ($conversation->state === 'queue_created') {
            $ticket = QueueTicket::query()->find($conversation->queue_ticket_id);

            return $ticket ? $this->ticketSummary($ticket, 'Antrean Anda') : 'Silakan scan ulang QR outlet.';
        }
        if (! $restaurant->active || ! $restaurant->queue_open) {
            $conversation->state = 'restaurant_selected';

            return "Antrean {$restaurant->name} sedang ditutup. Silakan hubungi staf outlet.";
        }
        if (mb_strtolower($text) === 'batal' || mb_strtolower($text) === 'tidak') {
            $conversation->fill(['state' => 'waiting_party_size', 'party_size' => null]);

            return "Pendaftaran belum dibuat. Untuk mulai lagi, balas jumlah tamu 1 sampai {$restaurant->max_party_size}.";
        }
        if ($conversation->state === 'waiting_party_size') {
            if (! ctype_digit($text) || (int) $text < 1 || (int) $text > $restaurant->max_party_size) {
                return "Jumlah tamu harus 1 sampai {$restaurant->max_party_size} orang. Balas dengan angka.";
            }
            $conversation->fill(['party_size' => (int) $text, 'state' => 'waiting_confirmation', 'expires_at' => now()->addMinutes(30)]);
            $snapshot = $this->queue->getRestaurantSnapshot($restaurant);

            return "Konfirmasi antrean:\nRestoran: {$restaurant->name}\nJumlah orang: {$conversation->party_size}\nSaat ini: {$snapshot['active_count']} antrean di depan Anda\nEstimasi: +/-{$snapshot['estimated_wait']} menit\n\nLanjut daftar? Balas YA untuk konfirmasi atau BATAL untuk mengubah jumlah.";
        }
        if ($conversation->state === 'waiting_confirmation') {
            if (mb_strtolower($text) !== 'ya') {
                return 'Balas YA untuk membuat antrean, atau BATAL untuk mengubah jumlah tamu.';
            }
            try {
                $ticket = $this->queue->createTicket($restaurant, phone: $conversation->phone_number, partySize: $conversation->party_size);
            } catch (ValidationException $exception) {
                $conversation->fill(['state' => 'waiting_party_size', 'party_size' => null]);

                return implode("\n", $exception->validator->errors()->all());
            }
            $conversation->fill(['state' => 'queue_created', 'queue_ticket_id' => $ticket->id]);

            return $this->ticketSummary($ticket, 'Berhasil! Antrean Anda sudah terdaftar.')."\nKami akan menghubungi Anda saat giliran semakin dekat.";
        }

        return 'Silakan scan ulang QR outlet untuk memulai pendaftaran.';
    }

    private function ticketSummary(QueueTicket $ticket, string $heading): string
    {
        $ticket->loadMissing('restaurant');
        $ahead = max(0, $this->queue->calculateCustomerPosition($ticket) - 1);
        $estimate = $this->queue->calculateEstimatedWait($ticket->restaurant, $ticket);
        $url = route('queue.show', ['ticket' => $ticket->public_token]);

        return "{$heading}\n\n{$ticket->restaurant->name}\nNomor: {$ticket->queue_number}\nJumlah tamu: {$ticket->party_size} orang\nStatus: {$ticket->status}\n{$ahead} antrean di depan Anda\nEstimasi: +/-{$estimate} menit\n\nLihat antrean: {$url}";
    }
}

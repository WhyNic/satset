<?php

namespace App\Services;

use App\Models\QueueTicket;
use App\Models\Restaurant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

class QueueService
{
    public function getActiveQueueCount(Restaurant $restaurant): int
    {
        return $restaurant->queueTickets()
            ->whereIn('status', [QueueTicket::STATUS_WAITING, QueueTicket::STATUS_CALLED])
            ->count();
    }

    public function calculateEstimatedWait(Restaurant $restaurant, ?QueueTicket $ticket = null): int
    {
        $peopleAhead = $ticket
            ? max($this->calculateCustomerPosition($ticket) - 1, 0)
            : $this->getActiveQueueCount($restaurant);

        return $peopleAhead * $restaurant->average_service_minutes;
    }

    public function calculateCustomerPosition(QueueTicket $ticket): int
    {
        if (! $ticket->isActive()) {
            return 0;
        }

        return QueueTicket::query()
            ->where('restaurant_id', $ticket->restaurant_id)
            ->whereIn('status', [QueueTicket::STATUS_WAITING, QueueTicket::STATUS_CALLED])
            ->where('sequence_number', '<=', $ticket->sequence_number)
            ->count();
    }

    public function generateQueueNumber(Restaurant $restaurant): array
    {
        $nextSequence = ((int) QueueTicket::query()
            ->where('restaurant_id', $restaurant->id)
            ->max('sequence_number')) + 1;

        return [
            'sequence_number' => $nextSequence,
            'queue_number' => sprintf('%s-%03d', strtoupper($restaurant->prefix), $nextSequence),
        ];
    }

    public function getRestaurantStatus(int $estimatedWait): string
    {
        return match (true) {
            $estimatedWait <= 15 => 'Normal',
            $estimatedWait <= 30 => 'Padat',
            default => 'Terlalu Padat',
        };
    }

    public function getRestaurantSnapshot(Restaurant $restaurant): array
    {
        $estimatedWait = $this->calculateEstimatedWait($restaurant);

        return [
            'active_count' => $this->getActiveQueueCount($restaurant),
            'waiting_count' => $restaurant->queueTickets()->where('status', QueueTicket::STATUS_WAITING)->count(),
            'current_number' => $restaurant->queueTickets()->where('status', QueueTicket::STATUS_CALLED)->value('queue_number'),
            'estimated_wait' => $estimatedWait,
            'status' => $this->getRestaurantStatus($estimatedWait),
            'available' => $restaurant->active && $restaurant->queue_open,
        ];
    }

    public function findActiveTicketForPhone(Restaurant $restaurant, string $phone): ?QueueTicket
    {
        return QueueTicket::query()
            ->where('restaurant_id', $restaurant->id)
            ->where('phone_number', $this->normalizePhoneNumber($phone))
            ->whereIn('status', [QueueTicket::STATUS_WAITING, QueueTicket::STATUS_CALLED])
            ->orderBy('sequence_number')
            ->first();
    }

    public function createTicket(Restaurant $restaurant, ?string $name = null, ?string $phone = null, int $partySize = 1): QueueTicket
    {
        return DB::transaction(function () use ($restaurant, $name, $phone, $partySize): QueueTicket {
            $restaurant = Restaurant::query()->lockForUpdate()->findOrFail($restaurant->id);
            if (! $restaurant->active || ! $restaurant->queue_open) {
                throw ValidationException::withMessages(['restaurant' => 'Antrean outlet sedang ditutup.']);
            }
            if ($partySize < 1 || $partySize > $restaurant->max_party_size) {
                throw ValidationException::withMessages(['party_size' => "Jumlah tamu harus 1 sampai {$restaurant->max_party_size} orang."]);
            }
            if ($phone && ($existing = $this->findActiveTicketForPhone($restaurant, $phone))) {
                return $existing;
            }
            $number = $this->generateQueueNumber($restaurant);

            return QueueTicket::query()->create([
                'restaurant_id' => $restaurant->id,
                'customer_name' => $name ?: null,
                'phone_number' => $phone ? $this->normalizePhoneNumber($phone) : null,
                'party_size' => $partySize,
                'queue_number' => $number['queue_number'],
                'sequence_number' => $number['sequence_number'],
                'status' => QueueTicket::STATUS_WAITING,
                'joined_at' => now(),
            ]);
        });
    }

    public function callNext(Restaurant $restaurant): ?QueueTicket
    {
        $ticket = DB::transaction(function () use ($restaurant): ?QueueTicket {
            $restaurant = Restaurant::query()->lockForUpdate()->findOrFail($restaurant->id);
            if ($restaurant->queueTickets()->where('status', QueueTicket::STATUS_CALLED)->exists()) {
                return null;
            }

            $ticket = $restaurant->queueTickets()
                ->where('status', QueueTicket::STATUS_WAITING)
                ->orderBy('sequence_number')
                ->lockForUpdate()
                ->first();

            if (! $ticket) {
                return null;
            }

            $ticket->update([
                'status' => QueueTicket::STATUS_CALLED,
                'called_at' => now(),
            ]);

            return $ticket->fresh('restaurant');
        });

        if ($ticket) {
            $this->afterChange($restaurant, $ticket, 'called');
        }

        return $ticket;
    }

    public function completeCurrent(Restaurant $restaurant): ?QueueTicket
    {
        return $this->transitionCurrent($restaurant, QueueTicket::STATUS_COMPLETED, 'completed_at');
    }

    public function skipCurrent(Restaurant $restaurant): ?QueueTicket
    {
        return $this->transitionCurrent($restaurant, QueueTicket::STATUS_SKIPPED, 'completed_at');
    }

    public function cancelTicket(QueueTicket $ticket): QueueTicket
    {
        $changed = DB::transaction(function () use ($ticket): bool {
            Restaurant::query()->whereKey($ticket->restaurant_id)->lockForUpdate()->firstOrFail();
            $fresh = QueueTicket::query()->lockForUpdate()->findOrFail($ticket->id);
            if (! $fresh->isActive()) {
                return false;
            }
            $fresh->update([
                'status' => QueueTicket::STATUS_CANCELLED,
                'cancelled_at' => now(),
            ]);

            return true;
        });
        $ticket = $ticket->fresh('restaurant');
        if ($changed) {
            $this->afterChange($ticket->restaurant, $ticket, 'cancelled');
        }

        return $ticket;
    }

    public function normalizePhoneNumber(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        return str_starts_with($digits, '0') ? '62'.substr($digits, 1) : $digits;
    }

    private function transitionCurrent(Restaurant $restaurant, string $status, string $timestampColumn): ?QueueTicket
    {
        $ticket = DB::transaction(function () use ($restaurant, $status, $timestampColumn): ?QueueTicket {
            Restaurant::query()->whereKey($restaurant->id)->lockForUpdate()->firstOrFail();
            $ticket = $restaurant->queueTickets()
                ->where('status', QueueTicket::STATUS_CALLED)
                ->orderBy('sequence_number')
                ->lockForUpdate()
                ->first();

            if (! $ticket) {
                return null;
            }

            $ticket->update([
                'status' => $status,
                $timestampColumn => now(),
            ]);

            return $ticket->fresh('restaurant');
        });

        if ($ticket) {
            $this->afterChange($restaurant);
        }

        return $ticket;
    }

    private function afterChange(Restaurant $restaurant, ?QueueTicket $ticket = null, ?string $event = null): void
    {
        try {
            $notifications = app(QueueWhatsAppNotificationService::class);
            if ($event === 'called') {
                $notifications->notifyCalled($ticket);
            } elseif ($event === 'cancelled') {
                $notifications->notifyCancelled($ticket);
            }
            $notifications->notifyWaitingPositions($restaurant, $this);
            app(WhatsAppService::class)->dispatchPending();
        } catch (Throwable) {
            Log::error('Queue updated but notification scheduling failed', ['restaurant_id' => $restaurant->id]);
        }
    }
}

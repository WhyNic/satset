<?php

namespace App\Services;

use App\Models\QueueTicket;
use App\Models\Restaurant;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

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
            'estimated_wait' => $estimatedWait,
            'status' => $this->getRestaurantStatus($estimatedWait),
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

    public function createTicket(Restaurant $restaurant, ?string $name = null, ?string $phone = null): QueueTicket
    {
        return DB::transaction(function () use ($restaurant, $name, $phone): QueueTicket {
            $restaurant = Restaurant::query()->lockForUpdate()->findOrFail($restaurant->id);
            $number = $this->generateQueueNumber($restaurant);

            return QueueTicket::query()->create([
                'restaurant_id' => $restaurant->id,
                'customer_name' => $name ?: null,
                'phone_number' => $phone ? $this->normalizePhoneNumber($phone) : null,
                'queue_number' => $number['queue_number'],
                'sequence_number' => $number['sequence_number'],
                'status' => QueueTicket::STATUS_WAITING,
                'joined_at' => now(),
            ]);
        });
    }

    public function callNext(Restaurant $restaurant): ?QueueTicket
    {
        return DB::transaction(function () use ($restaurant): ?QueueTicket {
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
        if ($ticket->isActive()) {
            $ticket->update([
                'status' => QueueTicket::STATUS_CANCELLED,
                'cancelled_at' => now(),
            ]);
        }

        return $ticket->fresh('restaurant');
    }

    public function getRecommendations(Restaurant $selected): Collection
    {
        return Restaurant::query()
            ->where('active', true)
            ->whereKeyNot($selected->id)
            ->where('category', $selected->category)
            ->get()
            ->map(function (Restaurant $restaurant): Restaurant {
                $restaurant->setAttribute('queue_snapshot', $this->getRestaurantSnapshot($restaurant));

                return $restaurant;
            })
            ->sortBy(fn (Restaurant $restaurant): int => $restaurant->getAttribute('queue_snapshot')['estimated_wait'])
            ->values();
    }

    public function normalizePhoneNumber(string $phone): string
    {
        return preg_replace('/\D+/', '', $phone) ?: $phone;
    }

    private function transitionCurrent(Restaurant $restaurant, string $status, string $timestampColumn): ?QueueTicket
    {
        return DB::transaction(function () use ($restaurant, $status, $timestampColumn): ?QueueTicket {
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
    }
}

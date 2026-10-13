<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\QueueTicket;
use App\Models\Restaurant;
use App\Services\QueueService;

class AdminDashboardController extends Controller
{
    public function __invoke(QueueService $queueService)
    {
        $restaurants = Restaurant::query()
            ->orderBy('name')
            ->get()
            ->map(function (Restaurant $restaurant) use ($queueService): Restaurant {
                $restaurant->setAttribute('queue_snapshot', $queueService->getRestaurantSnapshot($restaurant));
                $restaurant->setAttribute('current_number', $restaurant->queueTickets()->where('status', QueueTicket::STATUS_CALLED)->value('queue_number'));
                $restaurant->setAttribute('waiting_count', $restaurant->queueTickets()->where('status', QueueTicket::STATUS_WAITING)->count());

                return $restaurant;
            });

        $completedToday = QueueTicket::query()
            ->where('status', QueueTicket::STATUS_COMPLETED)
            ->whereDate('completed_at', today())
            ->whereNotNull('called_at')
            ->get();

        $averageWaiting = (int) round($completedToday->avg(fn (QueueTicket $ticket): int => max($ticket->joined_at?->diffInMinutes($ticket->called_at) ?? 0, 0)) ?? 0);

        return view('admin.dashboard', [
            'restaurants' => $restaurants,
            'totalRestaurants' => Restaurant::query()->count(),
            'activeQueue' => QueueTicket::query()->whereIn('status', [QueueTicket::STATUS_WAITING, QueueTicket::STATUS_CALLED])->count(),
            'customersToday' => QueueTicket::query()->whereDate('joined_at', today())->count(),
            'averageWaiting' => $averageWaiting,
        ]);
    }
}

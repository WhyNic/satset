<?php

namespace App\Http\Controllers;

use App\Models\Restaurant;
use App\Services\QueueService;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    public function __invoke(Request $request, QueueService $queueService)
    {
        $search = trim((string) $request->query('q', ''));

        $restaurants = Restaurant::query()
            ->where('active', true)
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('category', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->get()
            ->map(function (Restaurant $restaurant) use ($queueService): Restaurant {
                $restaurant->setAttribute('queue_snapshot', $queueService->getRestaurantSnapshot($restaurant));

                return $restaurant;
            });

        return view('home', compact('restaurants', 'search'));
    }
}

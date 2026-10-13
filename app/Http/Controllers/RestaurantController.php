<?php

namespace App\Http\Controllers;

use App\Models\Restaurant;
use App\Services\QueueService;

class RestaurantController extends Controller
{
    public function show(Restaurant $restaurant, QueueService $queueService)
    {
        abort_unless($restaurant->active, 404);

        $snapshot = $queueService->getRestaurantSnapshot($restaurant);

        return view('restaurants.show', compact('restaurant', 'snapshot'));
    }

    public function recommendations(Restaurant $restaurant, QueueService $queueService)
    {
        abort_unless($restaurant->active, 404);

        $snapshot = $queueService->getRestaurantSnapshot($restaurant);
        $recommendations = $queueService->getRecommendations($restaurant);

        return view('restaurants.recommendations', compact('restaurant', 'snapshot', 'recommendations'));
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Restaurant;
use App\Services\QueueService;
use App\Services\WhatsAppService;

class RestaurantController extends Controller
{
    public function show(Restaurant $restaurant, QueueService $queueService)
    {
        $snapshot = $queueService->getRestaurantSnapshot($restaurant);
        $whatsappUrl = app(WhatsAppService::class)->entryUrl($restaurant);

        return view('restaurants.show', compact('restaurant', 'snapshot', 'whatsappUrl'));
    }

    public function whatsapp(Restaurant $restaurant, WhatsAppService $whatsapp)
    {
        abort_unless($restaurant->active && $restaurant->queue_open, 409, 'Antrean outlet sedang ditutup.');
        $url = $whatsapp->entryUrl($restaurant);
        abort_unless($url, 503, 'Pendaftaran WhatsApp belum tersedia. Silakan hubungi staf outlet.');

        return redirect()->away($url);
    }

    public function status(Restaurant $restaurant, QueueService $queueService)
    {
        return response()->json($queueService->getRestaurantSnapshot($restaurant))->header('Cache-Control', 'no-store');
    }

    public function recommendations(Restaurant $restaurant, QueueService $queueService)
    {
        abort_unless($restaurant->active, 404);

        $snapshot = $queueService->getRestaurantSnapshot($restaurant);
        $recommendations = $queueService->getRecommendations($restaurant);

        return view('restaurants.recommendations', compact('restaurant', 'snapshot', 'recommendations'));
    }
}

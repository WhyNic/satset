<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;
use App\Models\Restaurant;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\SvgWriter;
use Endroid\QrCode\ErrorCorrectionLevel;

class AdminQrController extends Controller
{
    public function index(): View
    {
        return view('admin.qr.index', ['restaurants' => Restaurant::query()->orderBy('name')->get()]);
    }

    public function show(Restaurant $restaurant): View
    {
        return view('admin.qr.show', ['restaurant' => $restaurant, 'url' => $this->url($restaurant)]);
    }

    public function image(Request $request, Restaurant $restaurant): Response
    {
        $result = (new SvgWriter())->write(new QrCode(
            data: $this->url($restaurant), errorCorrectionLevel: ErrorCorrectionLevel::Medium,
            size: 360, margin: 32,
        ));

        return response($result->getString(), 200, [
            'Content-Type' => 'image/svg+xml',
            'Content-Disposition' => ($request->boolean('download') ? 'attachment' : 'inline').'; filename="satset-'.$restaurant->slug.'.svg"',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function url(Restaurant $restaurant): string
    {
        return rtrim(config('app.url'), '/').route('outlets.show', $restaurant->slug, false);
    }
}

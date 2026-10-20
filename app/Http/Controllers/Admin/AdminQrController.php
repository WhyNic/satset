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
    public function index(Request $request): View
    {
        return view('admin.qr.index', [
            'restaurants' => $request->user()->accessibleRestaurants()->orderBy('name')->get(),
            'baseUrl' => $this->baseUrl($request),
        ]);
    }

    public function show(Request $request, Restaurant $restaurant): View
    {
        return view('admin.qr.show', ['restaurant' => $restaurant, 'url' => $this->url($request, $restaurant)]);
    }

    public function image(Request $request, Restaurant $restaurant): Response
    {
        $result = (new SvgWriter())->write(new QrCode(
            data: $this->url($request, $restaurant), errorCorrectionLevel: ErrorCorrectionLevel::Medium,
            size: 360, margin: 32,
        ));

        return response($result->getString(), 200, [
            'Content-Type' => 'image/svg+xml',
            'Content-Disposition' => ($request->boolean('download') ? 'attachment' : 'inline').'; filename="satset-'.$restaurant->slug.'.svg"',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function url(Request $request, Restaurant $restaurant): string
    {
        return $this->baseUrl($request).route('outlets.show', $restaurant->slug, false);
    }

    /**
     * APP_URL, unless it still points at this computer while the admin is browsing via its
     * network address (e.g. http://192.168.1.10:8000). Then phones on the same Wi-Fi can scan it.
     */
    private function baseUrl(Request $request): string
    {
        $appUrl = rtrim((string) config('app.url'), '/');
        $local = ['localhost', '127.0.0.1', '::1', '[::1]'];

        if (in_array(parse_url($appUrl, PHP_URL_HOST), $local, true) && ! in_array($request->getHost(), $local, true)) {
            return $request->getSchemeAndHttpHost();
        }

        return $appUrl;
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Restaurant;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AdminRestaurantController extends Controller
{
    public function index()
    {
        return view('admin.restaurants.index', [
            'restaurants' => Restaurant::query()->orderBy('name')->get(),
        ]);
    }

    public function create()
    {
        return view('admin.restaurants.form', ['restaurant' => new Restaurant()]);
    }

    public function store(Request $request)
    {
        Restaurant::query()->create($this->validated($request));

        return redirect()->route('admin.restaurants.index')->with('status', 'Restoran berhasil dibuat.');
    }

    public function edit(Restaurant $restaurant)
    {
        return view('admin.restaurants.form', compact('restaurant'));
    }

    public function update(Request $request, Restaurant $restaurant)
    {
        if ($request->user()->isOwner()) {
            $restaurant->update($this->validated($request, $restaurant));

            return redirect()->route('admin.restaurants.index')->with('status', 'Restoran berhasil diperbarui.');
        }

        // Outlet admins may tune their outlet, but name, prefix and active status stay with the owner.
        $restaurant->update($this->validatedOutletSettings($request));

        return redirect()->route('admin.restaurants.queue', $restaurant)->with('status', 'Pengaturan outlet berhasil diperbarui.');
    }

    public function destroy(Restaurant $restaurant)
    {
        $restaurant->update(['active' => ! $restaurant->active]);

        return back()->with('status', 'Status restoran berhasil diubah.');
    }

    private function validatedOutletSettings(Request $request): array
    {
        $validated = $request->validate($this->outletSettingRules());
        $validated['queue_open'] = $request->boolean('queue_open');

        return $validated;
    }

    private function outletSettingRules(): array
    {
        return [
            'category' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:500'],
            'image' => ['nullable', 'string', 'max:255', function ($attribute, $value, $fail): void {
                if (! preg_match('~^(https://|/?images/)~i', $value)) {
                    $fail('Gunakan URL HTTPS atau path images/.');
                }
            }],
            'average_service_minutes' => ['required', 'integer', 'min:1', 'max:120'],
            'max_wait_minutes' => ['required', 'integer', 'min:1', 'max:240'],
            'max_party_size' => ['required', 'integer', 'min:1', 'max:100'],
            'queue_open' => ['nullable', 'boolean'],
        ];
    }

    private function validated(Request $request, ?Restaurant $restaurant = null): array
    {
        $id = $restaurant?->id;
        $request->merge(['prefix' => strtoupper((string) $request->input('prefix'))]);

        $validated = $request->validate([
            ...$this->outletSettingRules(),
            'name' => ['required', 'string', 'max:150'],
            'prefix' => ['required', 'string', 'regex:/^[A-Z0-9]{1,8}$/', Rule::unique('restaurants', 'prefix')->ignore($id), function ($attribute, $value, $fail) use ($restaurant): void {
                if ($restaurant && $value !== $restaurant->prefix && $restaurant->queueTickets()->exists()) {
                    $fail('Prefix tidak dapat diubah setelah memiliki riwayat antrean.');
                }
            }],
            'active' => ['nullable', 'boolean'],
        ]);

        $validated['prefix'] = strtoupper($validated['prefix']);
        $validated['slug'] = $restaurant?->slug ?? Str::slug($validated['name']).'-'.Str::lower((string) Str::ulid());
        $validated['active'] = $request->boolean('active');
        $validated['queue_open'] = $request->boolean('queue_open');

        return $validated;
    }
}

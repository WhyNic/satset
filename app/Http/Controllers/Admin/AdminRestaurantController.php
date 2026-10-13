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
        $restaurant->update($this->validated($request, $restaurant));

        return redirect()->route('admin.restaurants.index')->with('status', 'Restoran berhasil diperbarui.');
    }

    public function destroy(Restaurant $restaurant)
    {
        $restaurant->update(['active' => ! $restaurant->active]);

        return back()->with('status', 'Status restoran berhasil diubah.');
    }

    private function validated(Request $request, ?Restaurant $restaurant = null): array
    {
        $id = $restaurant?->id;

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'category' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:500'],
            'image' => ['nullable', 'string', 'max:255'],
            'prefix' => ['required', 'string', 'max:8', Rule::unique('restaurants', 'prefix')->ignore($id)],
            'average_service_minutes' => ['required', 'integer', 'min:1', 'max:120'],
            'max_wait_minutes' => ['required', 'integer', 'min:1', 'max:240'],
            'active' => ['nullable', 'boolean'],
        ]);

        $validated['slug'] = Str::slug($validated['name']).'-'.Str::upper($validated['prefix']);
        $validated['active'] = $request->boolean('active');

        return $validated;
    }
}

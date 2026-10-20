<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class AdminUserController extends Controller
{
    public function index()
    {
        return view('admin.users.index', [
            'users' => User::query()->with('restaurants:id,name')->orderByRaw('role = ? desc', [User::ROLE_OWNER])->orderBy('name')->get(),
        ]);
    }

    public function create()
    {
        return view('admin.users.form', [
            'user' => new User(['role' => User::ROLE_OUTLET_ADMIN, 'active' => true]),
            'restaurants' => Restaurant::query()->orderBy('name')->get(['id', 'name', 'active']),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        DB::transaction(function () use ($data): void {
            $user = User::query()->create($data['attributes']);
            $user->restaurants()->sync($data['restaurants']);
        });

        return redirect()->route('admin.users.index')->with('status', 'Akun admin berhasil dibuat.');
    }

    public function edit(User $user)
    {
        return view('admin.users.form', [
            'user' => $user->load('restaurants:id'),
            'restaurants' => Restaurant::query()->orderBy('name')->get(['id', 'name', 'active']),
        ]);
    }

    public function update(Request $request, User $user)
    {
        $data = $this->validated($request, $user);

        DB::transaction(function () use ($user, $data): void {
            $user->update($data['attributes']);
            $user->restaurants()->sync($data['restaurants']);
        });

        return redirect()->route('admin.users.index')->with('status', 'Akun admin berhasil diperbarui.');
    }

    /**
     * @return array{attributes: array<string, mixed>, restaurants: array<int, int>}
     */
    private function validated(Request $request, ?User $user = null): array
    {
        $isSelf = $user && $request->user()->is($user);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:150', Rule::unique('users', 'email')->ignore($user?->id)],
            'role' => ['required', Rule::in(array_keys(User::ROLE_LABELS))],
            'password' => [$user ? 'nullable' : 'required', 'confirmed', Password::min(8)],
            'active' => ['nullable', 'boolean'],
            'restaurants' => [$isSelf ? 'nullable' : 'required_if:role,'.User::ROLE_OUTLET_ADMIN, 'array'],
            'restaurants.*' => ['integer', Rule::exists('restaurants', 'id')],
        ], [
            'password.confirmed' => 'Konfirmasi password tidak sama.',
            'restaurants.required_if' => 'Pilih minimal satu outlet untuk admin outlet.',
        ]);

        $attributes = [
            'name' => $validated['name'],
            'email' => $validated['email'],
            'role' => $validated['role'],
            'active' => $request->boolean('active'),
        ];

        // Owners cannot lock themselves out by demoting or deactivating their own account.
        if ($isSelf) {
            $attributes['role'] = User::ROLE_OWNER;
            $attributes['active'] = true;
        }

        if (filled($validated['password'] ?? null)) {
            $attributes['password'] = $validated['password'];
        }

        $restaurants = $attributes['role'] === User::ROLE_OWNER ? [] : array_map('intval', $validated['restaurants'] ?? []);

        return ['attributes' => $attributes, 'restaurants' => $restaurants];
    }
}

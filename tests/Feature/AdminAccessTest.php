<?php

namespace Tests\Feature;

use App\Models\QueueTicket;
use App\Models\Restaurant;
use App\Models\User;
use App\Services\QueueService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AdminAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        config(['whatsapp.enabled' => false]);
    }

    public function test_outlet_admin_only_reaches_assigned_outlets(): void
    {
        $mine = $this->restaurant('Ayam Bakar', 'A');
        $other = $this->restaurant('Kopi Senja', 'K');
        $cashier = $this->cashier($mine);

        $this->actingAs($cashier);
        foreach ([
            route('admin.restaurants.queue', $mine),
            route('admin.qr.show', $mine),
            route('admin.restaurants.edit', $mine),
        ] as $url) {
            $this->get($url)->assertOk();
        }
        foreach ([
            route('admin.restaurants.queue', $other),
            route('admin.qr.show', $other),
            route('admin.qr.image', $other),
            route('admin.restaurants.edit', $other),
        ] as $url) {
            $this->get($url)->assertForbidden();
        }
        $this->post(route('admin.restaurants.queue.call-next', $other))->assertForbidden();
    }

    public function test_outlet_admin_cannot_open_owner_pages(): void
    {
        $cashier = $this->cashier($this->restaurant('Ayam Bakar', 'A'));

        $this->actingAs($cashier);
        foreach ([
            route('admin.restaurants.index'),
            route('admin.restaurants.create'),
            route('admin.users.index'),
            route('admin.users.create'),
            route('admin.whatsapp-logs.index'),
        ] as $url) {
            $this->get($url)->assertForbidden();
        }
        $this->post(route('admin.users.store'), [])->assertForbidden();
    }

    public function test_lists_are_scoped_to_assigned_outlets(): void
    {
        $mine = $this->restaurant('Ayam Bakar', 'A');
        $other = $this->restaurant('Kopi Senja', 'K');
        app(QueueService::class)->createTicket($mine, phone: '081111111111');
        app(QueueService::class)->createTicket($other, phone: '082222222222');

        $this->actingAs($this->cashier($mine));
        $this->get(route('admin.dashboard'))->assertOk()->assertSee('Ayam Bakar')->assertDontSee('Kopi Senja');
        $this->get(route('admin.queues.index'))->assertOk()->assertSee('A-001')->assertDontSee('K-001');
        $this->get(route('admin.qr.index'))->assertOk()->assertSee('Ayam Bakar')->assertDontSee('Kopi Senja');
        $this->get(route('admin.restaurants.queue', $mine))->assertOk()->assertDontSee('Akun admin');
    }

    public function test_outlet_admin_edits_settings_but_not_owner_fields(): void
    {
        $restaurant = $this->restaurant('Ayam Bakar', 'A');

        $this->actingAs($this->cashier($restaurant))->put(route('admin.restaurants.update', $restaurant), [
            'name' => 'Nama Baru', 'prefix' => 'Z', 'active' => '0',
            'category' => 'Ayam', 'description' => 'Menu baru', 'average_service_minutes' => 7,
            'max_wait_minutes' => 30, 'max_party_size' => 4, 'queue_open' => '0',
        ])->assertRedirect(route('admin.restaurants.queue', $restaurant));

        $restaurant->refresh();
        $this->assertSame('Ayam Bakar', $restaurant->name);
        $this->assertSame('A', $restaurant->prefix);
        $this->assertTrue($restaurant->active);
        $this->assertSame('Menu baru', $restaurant->description);
        $this->assertSame(7, $restaurant->average_service_minutes);
        $this->assertSame(4, $restaurant->max_party_size);
        $this->assertFalse($restaurant->queue_open);
    }

    public function test_cashier_with_one_outlet_lands_on_its_queue(): void
    {
        $restaurant = $this->restaurant('Ayam Bakar', 'A');
        $cashier = $this->cashier($restaurant, ['password' => bcrypt('kasir-password')]);

        $this->post(route('admin.login.store'), ['email' => $cashier->email, 'password' => 'kasir-password'])
            ->assertRedirect(route('admin.restaurants.queue', $restaurant));
    }

    public function test_deactivated_account_cannot_log_in_and_is_signed_out(): void
    {
        $restaurant = $this->restaurant('Ayam Bakar', 'A');
        $cashier = $this->cashier($restaurant, ['password' => bcrypt('kasir-password'), 'active' => false]);

        $this->post(route('admin.login.store'), ['email' => $cashier->email, 'password' => 'kasir-password'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();

        $this->actingAs($cashier)->get(route('admin.restaurants.queue', $restaurant))->assertRedirect(route('admin.login'));
        $this->assertGuest();
    }

    public function test_owner_creates_cashier_accounts_for_several_outlets(): void
    {
        $a = $this->restaurant('Ayam Bakar', 'A');
        $b = $this->restaurant('Kopi Senja', 'K');

        $this->actingAs(User::factory()->create())->post(route('admin.users.store'), [
            'name' => 'Kasir Pagi', 'email' => 'kasir@satset.test', 'role' => 'outlet_admin',
            'password' => 'rahasia-123', 'password_confirmation' => 'rahasia-123',
            'active' => '1', 'restaurants' => [$a->id, $b->id],
        ])->assertRedirect(route('admin.users.index'));

        $cashier = User::query()->where('email', 'kasir@satset.test')->firstOrFail();
        $this->assertFalse($cashier->isOwner());
        $this->assertEqualsCanonicalizing([$a->id, $b->id], $cashier->restaurants()->pluck('restaurants.id')->all());
    }

    public function test_outlet_admin_account_requires_an_outlet(): void
    {
        $this->actingAs(User::factory()->create())->post(route('admin.users.store'), [
            'name' => 'Kasir', 'email' => 'kasir@satset.test', 'role' => 'outlet_admin',
            'password' => 'rahasia-123', 'password_confirmation' => 'rahasia-123', 'active' => '1',
        ])->assertSessionHasErrors('restaurants');
        $this->assertDatabaseMissing('users', ['email' => 'kasir@satset.test']);
    }

    public function test_owner_cannot_demote_or_deactivate_themselves(): void
    {
        $owner = User::factory()->create();
        $restaurant = $this->restaurant('Ayam Bakar', 'A');

        $this->actingAs($owner)->put(route('admin.users.update', $owner), [
            'name' => $owner->name, 'email' => $owner->email, 'role' => 'outlet_admin',
            'active' => '0', 'restaurants' => [$restaurant->id],
        ])->assertRedirect(route('admin.users.index'));

        $owner->refresh();
        $this->assertTrue($owner->isOwner());
        $this->assertTrue($owner->active);
        $this->assertSame(0, $owner->restaurants()->count());
    }

    public function test_qr_uses_network_address_while_app_url_is_localhost(): void
    {
        config(['app.url' => 'http://localhost:8000']);
        $restaurant = $this->restaurant('Ayam Bakar', 'A');
        $this->actingAs(User::factory()->create());

        $this->get('http://192.168.1.10:8000/admin/restaurants/'.$restaurant->id.'/qr')
            ->assertOk()->assertSee('http://192.168.1.10:8000/r/'.$restaurant->slug)->assertDontSee('alamat lokal komputer');
        $this->get('http://localhost:8000/admin/restaurants/'.$restaurant->id.'/qr')->assertOk()->assertSee('alamat lokal komputer');

        config(['app.url' => 'https://satsetid.my.id']);
        $this->get('http://192.168.1.10:8000/admin/restaurants/'.$restaurant->id.'/qr')
            ->assertOk()->assertSee('https://satsetid.my.id/r/'.$restaurant->slug);
    }

    private function cashier(Restaurant $restaurant, array $attributes = []): User
    {
        $user = User::factory()->outletAdmin()->create($attributes);
        $user->restaurants()->attach($restaurant);

        return $user;
    }

    private function restaurant(string $name, string $prefix): Restaurant
    {
        return Restaurant::query()->create([
            'name' => $name, 'slug' => str($name)->slug().'-'.strtolower($prefix), 'category' => 'Makanan',
            'prefix' => $prefix, 'average_service_minutes' => 5, 'max_wait_minutes' => 30,
            'max_party_size' => 6, 'active' => true, 'queue_open' => true,
        ]);
    }
}

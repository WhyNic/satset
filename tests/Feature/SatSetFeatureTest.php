<?php

namespace Tests\Feature;

use App\Models\QueueTicket;
use App\Models\Restaurant;
use App\Models\User;
use App\Services\QueueService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SatSetFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        config(['whatsapp.enabled' => false]);
    }

    public function test_qr_landing_displays_stats_without_creating_ticket(): void
    {
        $restaurant = $this->restaurant();
        $queue = app(QueueService::class);
        $queue->createTicket($restaurant);
        $queue->createTicket($restaurant);
        $queue->callNext($restaurant);

        $this->get(route('outlets.show', $restaurant->slug))->assertOk()
            ->assertSee('Ayam Bakar Nusantara')->assertSee('A-001')->assertSee('Sedang menunggu');
        $this->getJson(route('outlets.status', $restaurant->slug))->assertOk()
            ->assertJson(['waiting_count' => 1, 'current_number' => 'A-001', 'estimated_wait' => 10, 'available' => true]);
        $this->assertDatabaseCount('queue_tickets', 2);
    }

    public function test_web_registration_is_removed_and_old_links_redirect(): void
    {
        $restaurant = $this->restaurant();
        $this->post("/restaurants/{$restaurant->id}/queue")->assertNotFound();
        $this->get("/restaurants/{$restaurant->id}/queue/new")->assertRedirect(route('outlets.show', $restaurant->slug));
        $this->get(route('restaurants.show', $restaurant))->assertRedirect(route('outlets.show', $restaurant->slug));
        $this->assertDatabaseCount('queue_tickets', 0);
    }

    public function test_queue_number_and_wait_estimate_remain_correct(): void
    {
        $restaurant = $this->restaurant();
        $queue = app(QueueService::class);
        $queue->createTicket($restaurant, partySize: 3);
        $queue->createTicket($restaurant);
        $third = $queue->createTicket($restaurant);

        $this->assertSame('A-003', $third->queue_number);
        $this->assertSame(3, $third->sequence_number);
        $this->assertSame(10, $queue->calculateEstimatedWait($restaurant, $third));
        $this->assertSame(3, QueueTicket::query()->count());
    }

    public function test_admin_can_call_complete_skip_cancel_and_update_positions(): void
    {
        $restaurant = $this->restaurant();
        $queue = app(QueueService::class);
        $first = $queue->createTicket($restaurant);
        $second = $queue->createTicket($restaurant);
        $third = $queue->createTicket($restaurant);
        $this->actingAs(User::factory()->create());

        $this->post(route('admin.restaurants.queue.call-next', $restaurant))->assertRedirect();
        $this->post(route('admin.restaurants.queue.call-next', $restaurant))->assertRedirect();
        $this->assertSame(1, QueueTicket::query()->where('status', 'called')->count());
        $this->post(route('admin.restaurants.queue.complete', $restaurant))->assertRedirect();
        $this->assertSame('completed', $first->fresh()->status);
        $this->assertSame(1, $queue->calculateCustomerPosition($second));
        $this->post(route('admin.restaurants.queue.call-next', $restaurant))->assertRedirect();
        $this->post(route('admin.restaurants.queue.skip', $restaurant))->assertRedirect();
        $this->assertSame('skipped', $second->fresh()->status);
        $this->post(route('admin.restaurants.queue.cancel', [$restaurant, $third]))->assertRedirect();
        $this->assertSame('cancelled', $third->fresh()->status);
        $this->assertSame(0, $queue->getActiveQueueCount($restaurant));
    }

    public function test_admin_cannot_cancel_ticket_belonging_to_another_outlet(): void
    {
        $restaurant = $this->restaurant();
        $other = $this->restaurant(['slug' => 'coffee', 'prefix' => 'C']);
        $ticket = app(QueueService::class)->createTicket($other);
        $this->actingAs(User::factory()->create())->post(route('admin.restaurants.queue.cancel', [$restaurant, $ticket]))->assertNotFound();
        $this->assertSame('waiting', $ticket->fresh()->status);
    }

    public function test_all_admin_pages_require_authentication_and_work_after_login(): void
    {
        $restaurant = $this->restaurant();
        $paths = ['/admin', '/admin/dashboard', '/admin/queues', '/admin/restaurants', '/admin/qr', "/admin/restaurants/{$restaurant->id}/queue", "/admin/restaurants/{$restaurant->id}/qr"];
        foreach ($paths as $path) {
            $this->get($path)->assertRedirect(route('admin.login'));
        }
        $admin = User::factory()->create(['password' => bcrypt('test-password')]);
        $this->post(route('admin.login.store'), ['email' => $admin->email, 'password' => 'test-password'])->assertRedirect();
        foreach (array_slice($paths, 1) as $path) {
            $this->get($path)->assertOk();
        }
        $this->post(route('admin.logout'))->assertRedirect(route('admin.login'));
        $this->get('/admin/dashboard')->assertRedirect(route('admin.login'));
    }

    public function test_restaurant_max_party_size_is_editable_and_qr_slug_is_stable(): void
    {
        $restaurant = $this->restaurant();
        $data = [...$restaurant->only(['name','category','prefix','average_service_minutes','max_wait_minutes','active','queue_open']), 'queue_mode' => 'seating'];
        $this->actingAs(User::factory()->create())->put(route('admin.restaurants.update', $restaurant), [
            ...$data, 'name' => 'Nama Baru', 'max_party_size' => 9,
        ])->assertRedirect(route('admin.restaurants.index'));
        $this->assertSame(9, $restaurant->fresh()->max_party_size);
        $this->assertSame('ayam-bakar-nusantara', $restaurant->fresh()->slug);
        $this->put(route('admin.restaurants.update', $restaurant), [...$data, 'max_party_size' => 0])->assertSessionHasErrors('max_party_size');
    }

    public function test_prefix_cannot_be_reused_after_tickets_exist(): void
    {
        $restaurant = $this->restaurant();
        app(QueueService::class)->createTicket($restaurant);
        $data = $restaurant->only(['name','category','average_service_minutes','max_wait_minutes','max_party_size','active','queue_open']);
        $this->actingAs(User::factory()->create())->put(route('admin.restaurants.update', $restaurant), [...$data, 'prefix' => 'B'])->assertSessionHasErrors('prefix');
    }

    public function test_qr_download_and_print_page_use_canonical_outlet_url(): void
    {
        config(['app.url' => 'https://queue.example.test']);
        $restaurant = $this->restaurant();
        $this->actingAs(User::factory()->create())->get(route('admin.qr.show', $restaurant))
            ->assertOk()->assertSee('https://queue.example.test/r/ayam-bakar-nusantara')->assertSee('Cetak QR');
        $response = $this->get(route('admin.qr.image', [$restaurant, 'download' => 1]));
        $response->assertOk()->assertHeader('Content-Type', 'image/svg+xml')
            ->assertHeader('Content-Disposition', 'attachment; filename="satset-ayam-bakar-nusantara.svg"');
        $this->assertStringContainsString('<svg', $response->getContent());
    }

    public function test_closed_outlet_remains_visible_but_cannot_join(): void
    {
        $restaurant = $this->restaurant(['queue_open' => false]);
        $this->get(route('outlets.show', $restaurant->slug))->assertOk()->assertSee('Antrean sedang ditutup');
        $this->get(route('outlets.whatsapp', $restaurant->slug))->assertConflict();
        $this->assertDatabaseCount('queue_tickets', 0);
    }

    public function test_public_ticket_refresh_does_not_create_ticket_or_expose_phone(): void
    {
        $restaurant = $this->restaurant();
        $ticket = app(QueueService::class)->createTicket($restaurant, phone: '081234567890', partySize: 3);
        $this->get(route('queue.show', $ticket->public_token))->assertOk()->assertSee('3 orang')->assertDontSee('6281234567890');
        $this->get(route('queue.show', $ticket->public_token))->assertOk();
        $this->getJson(route('queue.status', $ticket->public_token))->assertOk()->assertJsonMissingPath('phone_number');
        $this->assertDatabaseCount('queue_tickets', 1);
    }

    public function test_recommendations_are_same_category_and_sorted_by_wait(): void
    {
        $selected = $this->restaurant();
        $fast = $this->restaurant(['name'=>'Ayam cepat','slug'=>'ayam-cepat','prefix'=>'B']);
        $slow = $this->restaurant(['name'=>'Ayam lambat','slug'=>'ayam-lambat','prefix'=>'C']);
        $this->restaurant(['name'=>'Kopi','slug'=>'kopi','prefix'=>'D','category'=>'Kopi']);
        app(QueueService::class)->createTicket($slow);
        $recommendations = app(QueueService::class)->getRecommendations($selected);
        $this->assertSame([$fast->id, $slow->id], $recommendations->pluck('id')->all());
        $this->get(route('restaurants.recommendations', $selected))->assertOk()->assertSee('Ayam cepat');
    }

    public function test_outlet_name_is_escaped(): void
    {
        $restaurant = $this->restaurant(['name'=>'<script>alert(1)</script>']);
        $this->get(route('outlets.show', $restaurant->slug))->assertOk()
            ->assertSee('&lt;script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);
    }

    private function restaurant(array $overrides = []): Restaurant
    {
        return Restaurant::query()->create(array_merge([
            'name'=>'Ayam Bakar Nusantara', 'slug'=>'ayam-bakar-nusantara', 'category'=>'Ayam',
            'prefix'=>'A', 'average_service_minutes'=>5, 'max_wait_minutes'=>30,
            'max_party_size'=>6, 'active'=>true, 'queue_open'=>true,
        ], $overrides));
    }
}

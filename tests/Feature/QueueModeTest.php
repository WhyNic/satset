<?php

namespace Tests\Feature;

use App\Mail\QueueEmail;
use App\Models\Restaurant;
use App\Models\TicketEmailEvent;
use App\Models\User;
use App\Services\QueueService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * queue_mode decides what the customer is asked and told, and what staff see. Both sides must agree.
 */
class QueueModeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['email_alerts.enabled' => true, 'email_alerts.max_active_per_email' => 3, 'whatsapp.enabled' => false]);
        Mail::fake();
    }

    // ----- Customer side ------------------------------------------------------------------

    public function test_pickup_outlet_asks_only_for_an_email(): void
    {
        $restaurant = $this->restaurant(['queue_mode' => Restaurant::MODE_PICKUP]);

        $this->get(route('outlets.show', $restaurant->slug))
            ->assertSee('Isi email Anda')
            ->assertDontSee('Jumlah tamu')
            ->assertDontSee('orang per kelompok');

        $this->get(route('outlets.join', $restaurant->slug))
            ->assertOk()
            ->assertDontSee('name="party_size"', false)
            ->assertSee('name="email"', false);
    }

    public function test_seating_outlet_still_asks_for_party_size(): void
    {
        $restaurant = $this->restaurant();

        $this->get(route('outlets.show', $restaurant->slug))->assertSee('Isi jumlah tamu dan email');
        $this->get(route('outlets.join', $restaurant->slug))->assertSee('name="party_size"', false);
    }

    public function test_pickup_join_needs_only_an_email_and_creates_one_order(): void
    {
        $restaurant = $this->restaurant(['queue_mode' => Restaurant::MODE_PICKUP]);

        $this->post(route('outlets.join.store', $restaurant->slug), ['email' => 'tamu@contoh.test'])
            ->assertSessionHasNoErrors();

        $ticket = \App\Models\QueueTicket::query()->sole();
        $this->assertSame(1, $ticket->party_size);
        Mail::assertSent(QueueEmail::class, fn (QueueEmail $mail) => $mail->event === TicketEmailEvent::JOINED
            && ! str_contains($this->renderText($mail), 'Jumlah tamu'));
    }

    public function test_seating_join_still_requires_party_size(): void
    {
        $restaurant = $this->restaurant();

        $this->post(route('outlets.join.store', $restaurant->slug), ['email' => 'tamu@contoh.test'])
            ->assertSessionHasErrors('party_size');

        $this->assertDatabaseCount('queue_tickets', 0);
    }

    public function test_pickup_called_email_and_ticket_page_talk_about_pickup_not_seats(): void
    {
        $restaurant = $this->restaurant(['queue_mode' => Restaurant::MODE_PICKUP]);
        $queue = app(QueueService::class);
        $ticket = $queue->createTicket($restaurant, email: 'tamu@contoh.test');

        $queue->callNext($restaurant);

        Mail::assertSent(QueueEmail::class, function (QueueEmail $mail): bool {
            $text = $this->renderText($mail);

            return $mail->event === TicketEmailEvent::CALLED
                && str_contains($text, 'Silakan ambil di')
                && ! str_contains($text, 'menuju');
        });

        $this->get(route('queue.show', $ticket->fresh()->public_token))
            ->assertSee('Pesanan untuk diambil')
            ->assertSee('Pesanan Anda siap. Silakan ambil di staf outlet.')
            ->assertDontSee('1 orang');
    }

    // ----- Admin side and parity ----------------------------------------------------------

    public function test_admin_can_switch_the_mode_and_the_outlet_follows(): void
    {
        $restaurant = $this->restaurant();

        $this->actingAs(User::factory()->create())
            ->put(route('admin.restaurants.update', $restaurant), $this->payload(['queue_mode' => 'pickup']))
            ->assertSessionHasNoErrors();

        $this->assertSame(Restaurant::MODE_PICKUP, $restaurant->fresh()->queue_mode);
        $this->get(route('outlets.show', $restaurant->slug))->assertSee('Isi email Anda');
    }

    public function test_an_unknown_mode_is_rejected(): void
    {
        $restaurant = $this->restaurant();

        $this->actingAs(User::factory()->create())
            ->put(route('admin.restaurants.update', $restaurant), $this->payload(['queue_mode' => 'food-court']))
            ->assertSessionHasErrors('queue_mode');

        $this->assertSame(Restaurant::MODE_SEATING, $restaurant->fresh()->queue_mode);
    }

    public function test_admin_lists_show_the_mode_and_whether_the_customer_was_emailed(): void
    {
        $admin = User::factory()->create();
        $pickup = $this->restaurant(['queue_mode' => Restaurant::MODE_PICKUP]);
        $this->post(route('outlets.join.store', $pickup->slug), ['email' => 'tamu@contoh.test']);

        $this->actingAs($admin)->get(route('admin.restaurants.index'))->assertSee('Ambil di counter');

        $this->actingAs($admin)->get(route('admin.restaurants.queue', $pickup))
            ->assertSee('Pesanan')
            ->assertSee('1 pesanan')
            ->assertSee('Email terkirim: 1');

        $this->actingAs($admin)->get(route('admin.queues.index'))
            ->assertSee('Email terkirim: 1');
    }

    public function test_customer_and_admin_see_the_same_outcome_for_a_called_ticket(): void
    {
        $restaurant = $this->restaurant();
        $queue = app(QueueService::class);
        $ticket = $queue->createTicket($restaurant, partySize: 2, email: 'tamu@contoh.test');

        $this->actingAs(User::factory()->create())
            ->post(route('admin.restaurants.queue.call-next', $restaurant))
            ->assertRedirect();

        $this->get(route('queue.show', $ticket->fresh()->public_token))
            ->assertSee('Giliran Anda sudah tiba');
        $this->actingAs(User::factory()->create())
            ->get(route('admin.restaurants.queue', $restaurant))
            ->assertSee('Email terkirim: 1');
        Mail::assertSent(QueueEmail::class, fn (QueueEmail $mail) => $mail->event === TicketEmailEvent::CALLED);
    }

    // ----- Helpers ------------------------------------------------------------------------

    private function renderText(QueueEmail $mail): string
    {
        $content = $mail->content();

        return view($content->text, $content->with)->render();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Ayam Bakar Nusantara', 'category' => 'Ayam', 'description' => '', 'image' => '',
            'prefix' => 'A', 'average_service_minutes' => 5, 'max_wait_minutes' => 30, 'max_party_size' => 6,
            'active' => 1, 'queue_open' => 1, 'queue_mode' => 'seating',
        ], $overrides);
    }

    private function restaurant(array $overrides = []): Restaurant
    {
        return Restaurant::create(array_merge([
            'name' => 'Ayam Bakar Nusantara', 'slug' => 'ayam-bakar', 'prefix' => 'A', 'category' => 'Ayam',
            'average_service_minutes' => 5, 'max_wait_minutes' => 30, 'max_party_size' => 6, 'active' => true, 'queue_open' => true,
            'queue_mode' => Restaurant::MODE_SEATING,
        ], $overrides));
    }
}

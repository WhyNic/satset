<?php

namespace Tests\Feature;

use App\Jobs\SendQueueEmailJob;
use App\Mail\QueueEmail;
use App\Models\QueueTicket;
use App\Models\Restaurant;
use App\Models\TicketEmailEvent;
use App\Models\User;
use App\Services\QueueEmailNotificationService;
use App\Services\QueueService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Mail;
use Tests\Support\FlakyMailTransport;
use Tests\TestCase;
use Throwable;

/**
 * Whole-flow checks: customer and staff HTTP paths, UX contracts, and chaos runs where SMTP fails at random.
 */
class EmailFlowChaosTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['email_alerts.enabled' => true, 'email_alerts.max_active_per_email' => 3]);
        FlakyMailTransport::reset();
        $this->useMailer('flaky', 40);
    }

    // ----- Chaos -------------------------------------------------------------------------

    public function test_random_smtp_outages_and_queue_operations_still_deliver_each_email_once(): void
    {
        Bus::fake();
        $queue = app(QueueService::class);
        $notifications = app(QueueEmailNotificationService::class);
        $restaurant = $this->restaurant();
        $addresses = [];

        for ($i = 1; $i <= 6; $i++) {
            $addresses[] = "tamu{$i}@contoh.test";
            $notifications->joined($queue->createTicket($restaurant, email: "tamu{$i}@contoh.test", emailVerified: true));
        }

        mt_srand(777);
        for ($step = 0; $step < 80; $step++) {
            try {
                match (mt_rand(0, 3)) {
                    0 => $queue->callNext($restaurant),
                    1 => $queue->completeCurrent($restaurant),
                    2 => $queue->skipCurrent($restaurant),
                    default => $this->cancelRandomActive($queue, $restaurant),
                };
            } catch (Throwable) {
                // Cap and closed-outlet rejections are expected outcomes of random operations.
            }
        }

        // Replay every queued job like a worker: retry after each outage until it settles.
        $jobs = Bus::dispatched(SendQueueEmailJob::class);
        $this->assertGreaterThan(10, $jobs->count(), 'The workload should queue many alerts.');
        foreach ($jobs as $job) {
            $this->runUntilSettled($job);
        }

        $this->assertGreaterThan(0, FlakyMailTransport::$failures, 'The run must inject real outages.');

        $delivered = collect(FlakyMailTransport::$delivered);
        $this->assertSame(
            $delivered->count(),
            $delivered->map(fn (array $mail): string => $mail['to'].'|'.$mail['subject'])->unique()->count(),
            'An email was delivered more than once.'
        );
        foreach ($addresses as $address) {
            $this->assertTrue(
                $delivered->contains(fn (array $mail): bool => $mail['to'] === $address && str_starts_with($mail['subject'], 'Antrean ')),
                "No confirmation reached {$address} after the outages."
            );
        }
    }

    public function test_a_total_smtp_outage_never_stops_a_customer_from_joining(): void
    {
        $this->useMailer('down', 100);
        $restaurant = $this->restaurant();

        $this->post(route('outlets.join.store', $restaurant->slug), ['party_size' => 2, 'consent' => 1, 'email' => 'tamu@contoh.test'])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('queue_tickets', 1);
        $this->assertSame(0, TicketEmailEvent::query()->count(), 'Nothing may be marked as sent during an outage.');
    }

    // ----- Customer and staff paths over HTTP ---------------------------------------------

    public function test_customer_cancel_over_http_sends_one_notice_and_clears_the_email(): void
    {
        Mail::fake();
        $ticket = app(QueueService::class)->createTicket($this->restaurant(), email: 'tamu@contoh.test', emailVerified: true);

        $this->post(route('queue.cancel', $ticket->public_token))->assertRedirect();

        Mail::assertSent(QueueEmail::class, fn (QueueEmail $mail) => $mail->event === TicketEmailEvent::CANCELLED && $mail->hasTo('tamu@contoh.test'));
        $this->assertSame(QueueTicket::STATUS_CANCELLED, $ticket->fresh()->status);
        $this->assertNull($ticket->fresh()->email);
    }

    public function test_staff_call_next_over_http_notifies_the_customer(): void
    {
        Mail::fake();
        $restaurant = $this->restaurant();
        app(QueueService::class)->createTicket($restaurant, email: 'tamu@contoh.test', emailVerified: true);

        $this->actingAs(User::factory()->create())
            ->post(route('admin.restaurants.queue.call-next', $restaurant))
            ->assertRedirect();

        Mail::assertSent(QueueEmail::class, fn (QueueEmail $mail) => $mail->event === TicketEmailEvent::CALLED && $mail->hasTo('tamu@contoh.test'));
    }

    public function test_staff_complete_and_skip_over_http_clear_the_customer_email(): void
    {
        Mail::fake();
        $restaurant = $this->restaurant();
        $queue = app(QueueService::class);
        $first = $queue->createTicket($restaurant, email: 'satu@contoh.test', emailVerified: true);
        $second = $queue->createTicket($restaurant, email: 'dua@contoh.test', emailVerified: true);
        $admin = User::factory()->create();

        $this->actingAs($admin)->post(route('admin.restaurants.queue.call-next', $restaurant));
        $this->actingAs($admin)->post(route('admin.restaurants.queue.complete', $restaurant));
        $this->actingAs($admin)->post(route('admin.restaurants.queue.call-next', $restaurant));
        $this->actingAs($admin)->post(route('admin.restaurants.queue.skip', $restaurant));

        $this->assertNull($first->fresh()->email);
        $this->assertNull($second->fresh()->email);
    }

    // ----- UX contracts -------------------------------------------------------------------

    public function test_outlet_and_home_show_only_the_email_join(): void
    {
        $restaurant = $this->restaurant();

        $this->get(route('outlets.show', $restaurant->slug))
            ->assertOk()
            ->assertSee('Ambil antrean')
            ->assertSee(route('outlets.join', $restaurant->slug))
            ->assertDontSee('Lanjut via WhatsApp')
            ->assertDontSee('belum tersedia')
            ->assertDontSee('WhatsApp');

        $this->get(route('home'))->assertOk()->assertDontSee('WhatsApp');
    }

    public function test_admin_has_no_whatsapp_logs_page_or_menu(): void
    {
        $admin = User::factory()->create();

        $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk()->assertDontSee('WhatsApp Logs');
        $this->actingAs($admin)->get('/admin/whatsapp-logs')->assertNotFound();
    }

    public function test_ticket_page_names_the_channel_the_customer_will_actually_receive_updates_on(): void
    {
        $queue = app(QueueService::class);
        $withEmail = $queue->createTicket($this->restaurant(), email: 'tamu@contoh.test', emailVerified: true);
        $withoutEmail = $queue->createTicket($this->restaurant(['slug' => 'lain', 'prefix' => 'B']));

        $this->get(route('queue.show', $withEmail->public_token))
            ->assertSee('data-notify="email"', false)
            ->assertSee('dikirim melalui email')
            ->assertSee('t***@contoh.test')
            ->assertDontSee('WhatsApp');

        $this->get(route('queue.show', $withoutEmail->public_token))
            ->assertSee('data-notify="none"', false)
            ->assertDontSee('dikirim melalui email')
            ->assertDontSee('WhatsApp');
    }

    // ----- Helpers ------------------------------------------------------------------------

    private function useMailer(string $name, int $failurePercent): void
    {
        Mail::extend($name, fn () => new FlakyMailTransport($failurePercent, 20261021));
        config(['mail.default' => $name, "mail.mailers.{$name}" => ['transport' => $name]]);
        Mail::purge();
    }

    private function runUntilSettled(SendQueueEmailJob $job): void
    {
        for ($attempt = 1; $attempt <= 30; $attempt++) {
            try {
                $job->handle();

                return;
            } catch (Throwable) {
                // The queue would retry; the replay does the same.
            }
        }

        $this->fail("Ticket {$job->ticketId} event {$job->event} never settled after 30 attempts.");
    }

    private function cancelRandomActive(QueueService $queue, Restaurant $restaurant): void
    {
        $active = QueueTicket::query()
            ->where('restaurant_id', $restaurant->id)
            ->whereIn('status', [QueueTicket::STATUS_WAITING, QueueTicket::STATUS_CALLED])
            ->orderBy('id')
            ->get();

        if ($active->isNotEmpty()) {
            $queue->cancelTicket($active[mt_rand(0, $active->count() - 1)]);
        }
    }

    private function restaurant(array $overrides = []): Restaurant
    {
        return Restaurant::create(array_merge([
            'name' => 'Ayam Bakar Nusantara', 'slug' => 'ayam-bakar', 'prefix' => 'A', 'category' => 'Ayam',
            'average_service_minutes' => 5, 'max_wait_minutes' => 30, 'max_party_size' => 6, 'active' => true, 'queue_open' => true,
        ], $overrides));
    }
}

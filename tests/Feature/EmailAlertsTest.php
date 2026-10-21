<?php

namespace Tests\Feature;

use App\Jobs\SendQueueEmailJob;
use App\Mail\QueueEmail;
use App\Models\QueueTicket;
use App\Models\Restaurant;
use App\Models\TicketEmailEvent;
use App\Models\User;
use App\Services\QueueService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\TestWith;
use RuntimeException;
use Tests\TestCase;
use Throwable;

/**
 * Email alerts are tested the way high-traffic systems are: contract checks, injected faults,
 * and randomized operation sequences that assert invariants after every step.
 */
class EmailAlertsTest extends TestCase
{
    use RefreshDatabase;

    private const ACTIVE = [QueueTicket::STATUS_WAITING, QueueTicket::STATUS_CALLED];
    private const CLOSED = [QueueTicket::STATUS_COMPLETED, QueueTicket::STATUS_CANCELLED, QueueTicket::STATUS_SKIPPED];

    protected function setUp(): void
    {
        parent::setUp();
        config(['email_alerts.enabled' => true, 'email_alerts.max_active_per_email' => 3]);
    }

    // ----- Join contract -----------------------------------------------------------------

    public function test_opening_the_join_page_never_creates_a_ticket(): void
    {
        $restaurant = $this->restaurant();

        $this->get(route('outlets.join', $restaurant->slug))->assertOk();

        $this->assertDatabaseCount('queue_tickets', 0);
    }

    public function test_joining_creates_one_ticket_and_one_confirmation(): void
    {
        Mail::fake();
        $restaurant = $this->restaurant();

        $this->post(route('outlets.join.store', $restaurant->slug), [
            'party_size' => 3,
            'consent' => 1,
            'email' => 'tamu@contoh.test',
        ])->assertRedirect()->assertSessionHas('status');

        $ticket = QueueTicket::query()->sole();
        $this->assertSame('tamu@contoh.test', $ticket->email);
        $this->assertSame(3, $ticket->party_size);
        Mail::assertSent(QueueEmail::class, 1);
        Mail::assertSent(QueueEmail::class, fn (QueueEmail $mail) => $mail->event === TicketEmailEvent::JOINED && $mail->hasTo('tamu@contoh.test'));
    }

    public function test_invalid_email_and_party_size_are_rejected_without_a_ticket(): void
    {
        $restaurant = $this->restaurant();

        $cases = [
            ['party_size' => 2, 'email' => 'bukan-email'],
            ['party_size' => 2, 'email' => ''],
            ['party_size' => 2, 'email' => 'a@'],
            ['party_size' => 0, 'email' => 'ok@contoh.test'],
            ['party_size' => 7, 'email' => 'ok@contoh.test'],
            ['party_size' => 'banyak', 'email' => 'ok@contoh.test'],
        ];

        foreach ($cases as $payload) {
            $response = $this->post(route('outlets.join.store', $restaurant->slug), $payload);
            $response->assertSessionHasErrors();
        }

        $this->assertDatabaseCount('queue_tickets', 0);
    }

    public function test_closed_outlet_cannot_be_joined_by_email(): void
    {
        $restaurant = $this->restaurant(['queue_open' => false]);

        $this->post(route('outlets.join.store', $restaurant->slug), [
            'party_size' => 2,
            'consent' => 1,
            'email' => 'tamu@contoh.test',
        ])->assertSessionHasErrors('restaurant');

        $this->assertDatabaseCount('queue_tickets', 0);
    }

    public function test_one_email_holds_at_most_three_active_tickets_per_outlet(): void
    {
        Mail::fake();
        $restaurant = $this->restaurant();

        foreach (['Tamu@Contoh.test ', 'tamu@contoh.test', ' TAMU@contoh.test'] as $email) {
            $this->post(route('outlets.join.store', $restaurant->slug), ['party_size' => 1, 'consent' => 1, 'email' => $email])
                ->assertSessionDoesntHaveErrors();
        }

        $this->post(route('outlets.join.store', $restaurant->slug), ['party_size' => 1, 'consent' => 1, 'email' => 'tamu@contoh.test'])
            ->assertSessionHasErrors('email');

        $this->assertSame(3, QueueTicket::query()->where('email', 'tamu@contoh.test')->count());
    }

    public function test_completing_a_ticket_frees_a_slot_and_other_outlets_are_unaffected(): void
    {
        Mail::fake();
        $queue = app(QueueService::class);
        $restaurant = $this->restaurant();
        $other = $this->restaurant(['name' => 'Rice Bowl', 'slug' => 'rice-bowl', 'prefix' => 'C']);

        for ($i = 0; $i < 3; $i++) {
            $queue->createTicket($restaurant, email: 'tamu@contoh.test', emailVerified: true);
        }
        $queue->createTicket($other, email: 'tamu@contoh.test', emailVerified: true);

        $queue->callNext($restaurant);
        $queue->completeCurrent($restaurant);

        $this->post(route('outlets.join.store', $restaurant->slug), ['party_size' => 1, 'consent' => 1, 'email' => 'tamu@contoh.test'])
            ->assertSessionDoesntHaveErrors();
        $this->assertSame(3, QueueTicket::query()->where('restaurant_id', $restaurant->id)->whereIn('status', self::ACTIVE)->where('email', 'tamu@contoh.test')->count());
    }

    public function test_join_endpoint_is_rate_limited(): void
    {
        $restaurant = $this->restaurant();

        // Festival venues often share one public IP, so the limit must allow a crowd through.
        for ($i = 0; $i < 30; $i++) {
            $this->post(route('outlets.join.store', $restaurant->slug), ['party_size' => 0, 'consent' => 1, 'email' => 'x'])->assertStatus(302);
        }

        $this->post(route('outlets.join.store', $restaurant->slug), ['party_size' => 0, 'consent' => 1, 'email' => 'x'])->assertStatus(429);
    }

    // ----- Event delivery -----------------------------------------------------------------

    public function test_three_ahead_one_ahead_and_called_are_sent_once_each_to_the_right_ticket(): void
    {
        Mail::fake();
        $queue = app(QueueService::class);
        $restaurant = $this->restaurant();
        $tickets = [];
        foreach (['a', 'b', 'c', 'd', 'e', 'f'] as $name) {
            $tickets[$name] = $queue->createTicket($restaurant, email: "{$name}@contoh.test", emailVerified: true);
        }

        $queue->callNext($restaurant);   // A called; B one ahead; D three ahead
        $queue->completeCurrent($restaurant); // A done; C one ahead; E three ahead
        $queue->callNext($restaurant);   // B called

        $this->assertSentOnce('called', 'a@contoh.test');
        $this->assertSentOnce('one_ahead', 'b@contoh.test');
        $this->assertSentOnce('three_ahead', 'd@contoh.test');
        $this->assertSentOnce('one_ahead', 'c@contoh.test');
        $this->assertSentOnce('three_ahead', 'e@contoh.test');
        $this->assertSentOnce('called', 'b@contoh.test');
        Mail::assertNotSent(QueueEmail::class, fn (QueueEmail $mail) => $mail->hasTo('f@contoh.test'));
    }

    public function test_cancelling_sends_a_notice_and_then_clears_the_email(): void
    {
        Mail::fake();
        $queue = app(QueueService::class);
        $restaurant = $this->restaurant();
        $ticket = $queue->createTicket($restaurant, email: 'tamu@contoh.test', emailVerified: true);

        $queue->cancelTicket($ticket);

        $this->assertSentOnce('cancelled', 'tamu@contoh.test');
        $this->assertNull($ticket->fresh()->email);
    }

    public function test_completed_and_skipped_tickets_lose_their_email_but_waiting_tickets_keep_it(): void
    {
        Mail::fake();
        $queue = app(QueueService::class);
        $restaurant = $this->restaurant();
        $first = $queue->createTicket($restaurant, email: 'satu@contoh.test', emailVerified: true);
        $second = $queue->createTicket($restaurant, email: 'dua@contoh.test', emailVerified: true);
        $third = $queue->createTicket($restaurant, email: 'tiga@contoh.test', emailVerified: true);

        $queue->callNext($restaurant);
        $queue->completeCurrent($restaurant);
        $queue->callNext($restaurant);
        $queue->skipCurrent($restaurant);

        $this->assertNull($first->fresh()->email);
        $this->assertNull($second->fresh()->email);
        $this->assertSame('tiga@contoh.test', $third->fresh()->email);
    }

    // ----- Fault injection ----------------------------------------------------------------

    public function test_smtp_outage_never_breaks_the_queue_action(): void
    {
        Log::spy();
        $queue = app(QueueService::class);
        $restaurant = $this->restaurant();
        $queue->createTicket($restaurant, email: 'a@contoh.test', emailVerified: true);
        $queue->createTicket($restaurant, email: 'b@contoh.test', emailVerified: true);

        Mail::shouldReceive('to')->andThrow(new RuntimeException('SMTP unreachable'));

        $called = $queue->callNext($restaurant);

        $this->assertSame(QueueTicket::STATUS_CALLED, $called->status);
        $this->assertSame(0, TicketEmailEvent::query()->whereNotNull('sent_at')->count());
        $this->assertSame(0, TicketEmailEvent::query()->count(), 'Failed claims must be released so a retry can send.');
        Log::shouldHaveReceived('warning')->with('Queue email not sent, will retry', \Mockery::type('array'));
    }

    public function test_a_failed_send_is_retried_and_then_sent_exactly_once(): void
    {
        $queue = app(QueueService::class);
        $restaurant = $this->restaurant();
        $ticket = $queue->createTicket($restaurant, email: 'tamu@contoh.test', emailVerified: true);

        // Real transport failure: nothing listens on port 1, so the SMTP connection is refused.
        config([
            'mail.default' => 'broken',
            'mail.mailers.broken' => ['transport' => 'smtp', 'host' => '127.0.0.1', 'port' => 1, 'timeout' => 1],
        ]);
        Mail::purge('broken');
        try {
            (new SendQueueEmailJob($ticket->id, TicketEmailEvent::JOINED, 'tamu@contoh.test'))->handle();
            $this->fail('The job must surface the failure so the queue retries it.');
        } catch (Throwable) {
        }
        $this->assertSame(0, TicketEmailEvent::query()->count(), 'The claim must be released for the retry.');

        Mail::fake();
        (new SendQueueEmailJob($ticket->id, TicketEmailEvent::JOINED, 'tamu@contoh.test'))->handle();
        (new SendQueueEmailJob($ticket->id, TicketEmailEvent::JOINED, 'tamu@contoh.test'))->handle();

        Mail::assertSentCount(1);
    }

    public function test_duplicate_deliveries_of_the_same_event_send_once(): void
    {
        Mail::fake();
        $queue = app(QueueService::class);
        $ticket = $queue->createTicket($this->restaurant(), email: 'tamu@contoh.test', emailVerified: true);

        for ($i = 0; $i < 3; $i++) {
            (new SendQueueEmailJob($ticket->id, TicketEmailEvent::JOINED, 'tamu@contoh.test'))->handle();
        }

        Mail::assertSentCount(1);
    }

    public function test_kill_switch_stops_sending_but_the_queue_keeps_working(): void
    {
        Mail::fake();
        config(['email_alerts.enabled' => false]);
        $queue = app(QueueService::class);
        $restaurant = $this->restaurant();

        $this->post(route('outlets.join.store', $restaurant->slug), ['party_size' => 2, 'consent' => 1, 'email' => 'tamu@contoh.test'])
            ->assertSessionDoesntHaveErrors();
        $queue->callNext($restaurant);

        Mail::assertNothingSent();
        $this->assertSame(QueueTicket::STATUS_CALLED, QueueTicket::query()->sole()->status);
    }

    public function test_a_stale_claim_from_a_crashed_worker_is_reclaimed(): void
    {
        Mail::fake();
        $ticket = app(QueueService::class)->createTicket($this->restaurant(), email: 'tamu@contoh.test', emailVerified: true);
        TicketEmailEvent::query()->create(['queue_ticket_id' => $ticket->id, 'event' => TicketEmailEvent::JOINED]);
        DB::table('ticket_email_events')->update(['created_at' => now()->subHour()]);

        (new SendQueueEmailJob($ticket->id, TicketEmailEvent::JOINED, 'tamu@contoh.test'))->handle();

        Mail::assertSentCount(1);
    }

    public function test_a_fresh_in_flight_claim_blocks_a_second_send(): void
    {
        Mail::fake();
        $ticket = app(QueueService::class)->createTicket($this->restaurant(), email: 'tamu@contoh.test', emailVerified: true);
        TicketEmailEvent::query()->create(['queue_ticket_id' => $ticket->id, 'event' => TicketEmailEvent::JOINED]);

        (new SendQueueEmailJob($ticket->id, TicketEmailEvent::JOINED, 'tamu@contoh.test'))->handle();

        Mail::assertNothingSent();
    }

    // ----- Privacy -----------------------------------------------------------------------

    public function test_full_email_never_appears_on_public_or_admin_pages(): void
    {
        Mail::fake();
        $restaurant = $this->restaurant();
        $this->post(route('outlets.join.store', $restaurant->slug), ['party_size' => 2, 'consent' => 1, 'email' => 'rahasia.tamu@contoh.test']);
        $ticket = QueueTicket::query()->sole();

        $this->get(route('outlets.show', $restaurant->slug))->assertDontSee('rahasia.tamu@contoh.test');
        $this->get(route('queue.show', $ticket->public_token))
            ->assertDontSee('rahasia.tamu@contoh.test')
            ->assertSee('r***********@contoh.test');

        $this->actingAs(User::factory()->create());
        $this->get(route('admin.restaurants.queue', $restaurant))->assertDontSee('rahasia.tamu@contoh.test');
        $this->get(route('admin.queues.index'))->assertDontSee('rahasia.tamu@contoh.test');
    }

    public function test_every_email_template_renders_html_and_text_with_the_ticket_link(): void
    {
        $queue = app(QueueService::class);
        $ticket = $queue->createTicket($this->restaurant(), partySize: 2, email: 'tamu@contoh.test', emailVerified: true);

        foreach ([TicketEmailEvent::JOINED, TicketEmailEvent::THREE_AHEAD, TicketEmailEvent::ONE_AHEAD, TicketEmailEvent::CALLED, TicketEmailEvent::CANCELLED] as $event) {
            $content = (new QueueEmail($ticket->fresh('restaurant'), $event))->content();
            $html = view($content->view, $content->with)->render();
            $text = view($content->text, $content->with)->render();

            $this->assertStringContainsString($ticket->queue_number, $html, "{$event} HTML misses the queue number.");
            $this->assertStringContainsString(route('queue.show', ['ticket' => $ticket->public_token]), $html, "{$event} HTML misses the link.");
            $this->assertStringContainsString($ticket->queue_number, $text, "{$event} text misses the queue number.");
            $this->assertStringNotContainsString('<html', $text, "{$event} text version contains HTML.");
        }
    }

    public function test_outdated_alerts_are_settled_without_sending(): void
    {
        Mail::fake();
        $queue = app(QueueService::class);
        $restaurant = $this->restaurant();
        $first = $queue->createTicket($restaurant, email: 'pertama@contoh.test', emailVerified: true);
        $second = $queue->createTicket($restaurant, email: 'kedua@contoh.test', emailVerified: true);
        $queue->callNext($restaurant); // $first is now called and $second is still waiting.
        Mail::fake(); // Only the outdated jobs below may be observed.

        // "called" for a ticket still waiting is outdated; "three ahead" for a called ticket is outdated too.
        (new SendQueueEmailJob($second->id, TicketEmailEvent::CALLED, 'kedua@contoh.test'))->handle();
        (new SendQueueEmailJob($first->id, TicketEmailEvent::THREE_AHEAD, 'pertama@contoh.test'))->handle();

        Mail::assertNothingSent();
        $this->assertNotNull(TicketEmailEvent::query()->where(['queue_ticket_id' => $second->id, 'event' => TicketEmailEvent::CALLED])->value('sent_at'));
        $this->assertNotNull(TicketEmailEvent::query()->where(['queue_ticket_id' => $first->id, 'event' => TicketEmailEvent::THREE_AHEAD])->value('sent_at'));
    }

    public function test_masking_and_normalization_helpers(): void
    {
        $this->assertSame('t***@contoh.test', (new QueueTicket(['email' => 'tamu@contoh.test']))->maskedEmail());
        $this->assertSame('a*@x.id', (new QueueTicket(['email' => 'ab@x.id']))->maskedEmail());
        $this->assertSame('a*@x.id', (new QueueTicket(['email' => 'a@x.id']))->maskedEmail());
        $this->assertNull((new QueueTicket(['email' => null]))->maskedEmail());
        $this->assertSame('tamu@contoh.test', app(QueueService::class)->normalizeEmail("  TAMU@Contoh.test \n"));
    }

    // ----- Randomized operation sequences --------------------------------------------------

    #[TestWith([20261021])]
    #[TestWith([7])]
    #[TestWith([424242])]
    #[TestWith([1])]
    public function test_random_operation_sequences_keep_every_invariant_at_every_step(int $seed): void
    {
        Mail::fake();
        mt_srand($seed);
        $queue = app(QueueService::class);
        $restaurant = $this->restaurant();
        $addresses = ['a@tamu.test', 'b@tamu.test', 'c@tamu.test', 'd@tamu.test', 'e@tamu.test'];

        for ($step = 0; $step < 300; $step++) {
            try {
                match (mt_rand(0, 9)) {
                    0, 1, 2, 3, 4 => $queue->createTicket($restaurant, partySize: mt_rand(1, 6), email: $addresses[mt_rand(0, 4)], emailVerified: true),
                    5 => $queue->callNext($restaurant),
                    6 => $queue->completeCurrent($restaurant),
                    7 => $queue->skipCurrent($restaurant),
                    default => $this->cancelRandomActive($queue, $restaurant),
                };
            } catch (ValidationException) {
                // The per-email cap is an expected outcome, not a failure.
            }

            $this->assertInvariants($restaurant, $addresses, $step);
        }

        $this->assertGreaterThan(0, Mail::sent(QueueEmail::class)->count(), 'The sequence should exercise sending.');
    }

    // ----- Helpers ------------------------------------------------------------------------

    private function restaurant(array $overrides = []): Restaurant
    {
        return Restaurant::create(array_merge([
            'name' => 'Ayam Bakar Nusantara', 'slug' => 'ayam-bakar', 'prefix' => 'A', 'category' => 'Ayam',
            'average_service_minutes' => 5, 'max_wait_minutes' => 30, 'max_party_size' => 6, 'active' => true, 'queue_open' => true,
        ], $overrides));
    }

    private function assertSentOnce(string $event, string $email): void
    {
        $matches = Mail::sent(QueueEmail::class, fn (QueueEmail $mail) => $mail->event === $event && $mail->hasTo($email));
        $this->assertCount(1, $matches, "Expected exactly one {$event} email to {$email}.");
    }

    private function cancelRandomActive(QueueService $queue, Restaurant $restaurant): void
    {
        $active = QueueTicket::query()->where('restaurant_id', $restaurant->id)->whereIn('status', self::ACTIVE)->orderBy('id')->get();
        if ($active->isNotEmpty()) {
            $queue->cancelTicket($active[mt_rand(0, $active->count() - 1)]);
        }
    }

    /**
     * @param  list<string>  $addresses
     */
    private function assertInvariants(Restaurant $restaurant, array $addresses, int $step): void
    {
        $leaked = QueueTicket::query()->where('restaurant_id', $restaurant->id)->whereIn('status', self::CLOSED)->whereNotNull('email')->count();
        $this->assertSame(0, $leaked, "Step {$step}: a closed ticket still holds an email.");

        foreach ($addresses as $address) {
            $active = QueueTicket::query()->where('restaurant_id', $restaurant->id)->whereIn('status', self::ACTIVE)->where('email', $address)->count();
            $this->assertLessThanOrEqual(3, $active, "Step {$step}: {$address} exceeds the active cap.");
        }

        $perTicketEvent = Mail::sent(QueueEmail::class)
            ->map(fn (QueueEmail $mail): string => $mail->ticket->id.':'.$mail->event)
            ->countBy();
        foreach ($perTicketEvent as $key => $count) {
            $this->assertSame(1, $count, "Step {$step}: {$key} was sent {$count} times.");
        }
    }
}

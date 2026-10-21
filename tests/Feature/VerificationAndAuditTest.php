<?php

namespace Tests\Feature;

use App\Mail\QueueEmail;
use App\Models\QueueTicket;
use App\Models\Restaurant;
use App\Models\StaffAction;
use App\Models\TicketEmailEvent;
use App\Models\User;
use App\Services\QueueEmailNotificationService;
use App\Services\QueueService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Email ownership, consent, staff resend, and the staff activity trail.
 */
class VerificationAndAuditTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['email_alerts.enabled' => true, 'email_alerts.max_active_per_email' => 3]);
    }

    // ----- Consent and privacy ------------------------------------------------------------

    public function test_joining_without_consent_is_refused(): void
    {
        $restaurant = $this->restaurant();

        $this->post(route('outlets.join.store', $restaurant->slug), ['party_size' => 2, 'email' => 'tamu@contoh.test'])
            ->assertSessionHasErrors('consent');

        $this->assertDatabaseCount('queue_tickets', 0);
    }

    public function test_privacy_page_explains_retention_and_verification(): void
    {
        $this->get(route('privacy'))
            ->assertOk()
            ->assertSee('dihapus')
            ->assertSee('verifikasi');
    }

    // ----- Verification -------------------------------------------------------------------

    public function test_confirmation_carries_the_link_and_only_the_hash_is_stored(): void
    {
        Mail::fake();
        $restaurant = $this->restaurant();
        $this->join($restaurant, 'tamu@contoh.test');

        $ticket = QueueTicket::query()->sole();
        $token = $this->tokenFor('tamu@contoh.test');

        $this->assertNull($ticket->email_verified_at);
        $this->assertSame(hash('sha256', $token), $ticket->email_verification_hash);
        $this->assertNotSame($token, $ticket->email_verification_hash);
        $this->assertArrayNotHasKey('email_verification_hash', $ticket->toArray());
    }

    public function test_the_link_verifies_the_email_and_clears_the_hash(): void
    {
        Mail::fake();
        $restaurant = $this->restaurant();
        $this->join($restaurant, 'tamu@contoh.test');
        $ticket = QueueTicket::query()->sole();

        $this->get($this->verifyUrl($ticket, $this->tokenFor('tamu@contoh.test')))
            ->assertRedirect(route('queue.show', $ticket->public_token))
            ->assertSessionHas('status');

        $ticket->refresh();
        $this->assertTrue($ticket->isEmailVerified());
        $this->assertNull($ticket->email_verification_hash);
    }

    public function test_a_wrong_token_is_not_found_and_changes_nothing(): void
    {
        Mail::fake();
        $restaurant = $this->restaurant();
        $this->join($restaurant, 'tamu@contoh.test');
        $ticket = QueueTicket::query()->sole();
        $before = $ticket->email_verification_hash;

        $this->get($this->verifyUrl($ticket, 'token-salah'))->assertNotFound();

        $ticket->refresh();
        $this->assertFalse($ticket->isEmailVerified());
        $this->assertSame($before, $ticket->email_verification_hash);
    }

    public function test_a_used_link_is_harmless(): void
    {
        Mail::fake();
        $restaurant = $this->restaurant();
        $this->join($restaurant, 'tamu@contoh.test');
        $ticket = QueueTicket::query()->sole();
        $url = $this->verifyUrl($ticket, $this->tokenFor('tamu@contoh.test'));

        $this->get($url);
        $this->get($url)->assertRedirect()->assertSessionHas('status', 'Email ini sudah terverifikasi.');

        $this->assertTrue($ticket->fresh()->isEmailVerified());
    }

    public function test_unverified_customers_get_no_alerts_until_they_verify_then_the_current_ones_arrive(): void
    {
        Mail::fake();
        $restaurant = $this->restaurant();
        $queue = app(QueueService::class);
        $queue->createTicket($restaurant, email: 'a@contoh.test', emailVerified: true);
        $queue->createTicket($restaurant, email: 'b@contoh.test', emailVerified: true);
        $queue->createTicket($restaurant, email: 'c@contoh.test', emailVerified: true);

        $this->join($restaurant, 'tamu@contoh.test');   // three ahead, but not verified yet
        $this->assertSame(0, $this->countFor('tamu@contoh.test', TicketEmailEvent::THREE_AHEAD));

        $ticket = QueueTicket::query()->where('email', 'tamu@contoh.test')->sole();
        $this->get($this->verifyUrl($ticket, $this->tokenFor('tamu@contoh.test')));

        $this->assertSame(1, $this->countFor('tamu@contoh.test', TicketEmailEvent::THREE_AHEAD));
    }

    public function test_a_called_unverified_customer_is_told_once_verified(): void
    {
        Mail::fake();
        $restaurant = $this->restaurant();
        $queue = app(QueueService::class);
        $this->join($restaurant, 'tamu@contoh.test');
        $queue->callNext($restaurant);

        $this->assertSame(0, $this->countFor('tamu@contoh.test', TicketEmailEvent::CALLED));

        $ticket = QueueTicket::query()->where('email', 'tamu@contoh.test')->sole();
        $this->get($this->verifyUrl($ticket, $this->tokenFor('tamu@contoh.test')));

        $this->assertSame(1, $this->countFor('tamu@contoh.test', TicketEmailEvent::CALLED));
    }

    public function test_resending_verification_replaces_the_link(): void
    {
        Mail::fake();
        $restaurant = $this->restaurant();
        $this->join($restaurant, 'tamu@contoh.test');
        $ticket = QueueTicket::query()->sole();
        $oldToken = $this->tokenFor('tamu@contoh.test');

        $this->post(route('queue.verification.resend', $ticket->public_token))->assertRedirect();

        $newToken = $this->tokenFor('tamu@contoh.test');
        $this->assertNotSame($oldToken, $newToken);
        $this->assertSame(2, $this->countFor('tamu@contoh.test', TicketEmailEvent::JOINED));
        $this->get($this->verifyUrl($ticket, $oldToken))->assertNotFound();
        $this->get($this->verifyUrl($ticket, $newToken))->assertRedirect();
    }

    public function test_resending_for_a_verified_ticket_does_nothing(): void
    {
        Mail::fake();
        $ticket = app(QueueService::class)->createTicket($this->restaurant(), email: 'tamu@contoh.test', emailVerified: true);

        $this->post(route('queue.verification.resend', $ticket->public_token))
            ->assertSessionHas('status', 'Email ini sudah terverifikasi atau antrean sudah berakhir.');

        Mail::assertNothingSent();
    }

    public function test_a_repeated_confirmation_never_unverifies_a_verified_email(): void
    {
        Mail::fake();
        $ticket = app(QueueService::class)->createTicket($this->restaurant(), email: 'tamu@contoh.test', emailVerified: true);

        app(QueueEmailNotificationService::class)->joined($ticket);

        $this->assertTrue($ticket->fresh()->isEmailVerified());
    }

    public function test_the_ticket_page_asks_for_verification_and_drops_the_prompt_once_verified(): void
    {
        Mail::fake();
        $restaurant = $this->restaurant();
        $this->join($restaurant, 'tamu@contoh.test');
        $ticket = QueueTicket::query()->sole();

        $this->get(route('queue.show', $ticket->public_token))
            ->assertSee('Kirim ulang tautan verifikasi')
            ->assertSee('menunggu verifikasi');

        $this->get($this->verifyUrl($ticket, $this->tokenFor('tamu@contoh.test')));

        $this->get(route('queue.show', $ticket->public_token))
            ->assertDontSee('Kirim ulang tautan verifikasi')
            ->assertDontSee('menunggu verifikasi');
    }

    // ----- Staff resend and audit ---------------------------------------------------------

    public function test_staff_resend_sends_the_email_that_matches_the_current_state(): void
    {
        Mail::fake();
        $restaurant = $this->restaurant();
        $queue = app(QueueService::class);
        $queue->createTicket($restaurant, email: 'a@contoh.test', emailVerified: true);
        $queue->createTicket($restaurant, email: 'b@contoh.test', emailVerified: true);
        $queue->createTicket($restaurant, email: 'c@contoh.test', emailVerified: true);
        $ticket = $queue->createTicket($restaurant, email: 'tamu@contoh.test', emailVerified: true);
        $admin = User::factory()->create();

        $this->actingAs($admin)
            ->post(route('admin.restaurants.queue.resend-email', [$restaurant, $ticket]))
            ->assertSessionHas('status', 'Email untuk '.$ticket->queue_number.' dikirim ulang.');

        $this->assertSame(1, $this->countFor('tamu@contoh.test', TicketEmailEvent::THREE_AHEAD));
        $this->assertDatabaseHas('staff_actions', ['user_id' => $admin->id, 'queue_ticket_id' => $ticket->id, 'action' => 'resend-email']);
    }

    public function test_staff_resend_for_an_unverified_ticket_sends_the_verification_link(): void
    {
        Mail::fake();
        $restaurant = $this->restaurant();
        $this->join($restaurant, 'tamu@contoh.test');
        $ticket = QueueTicket::query()->sole();

        $this->actingAs(User::factory()->create())
            ->post(route('admin.restaurants.queue.resend-email', [$restaurant, $ticket]));

        $this->assertSame(2, $this->countFor('tamu@contoh.test', TicketEmailEvent::JOINED));
    }

    public function test_staff_resend_with_nothing_to_send_says_so_and_leaves_no_trail(): void
    {
        Mail::fake();
        $restaurant = $this->restaurant();
        $ticket = app(QueueService::class)->createTicket($restaurant, email: 'tamu@contoh.test', emailVerified: true);

        $this->actingAs(User::factory()->create())
            ->post(route('admin.restaurants.queue.resend-email', [$restaurant, $ticket]))
            ->assertSessionHas('status', 'Tidak ada email yang perlu dikirim ulang untuk '.$ticket->queue_number.'.');

        $this->assertDatabaseMissing('staff_actions', ['action' => 'resend-email']);
    }

    public function test_every_staff_action_is_recorded_with_who_did_it(): void
    {
        Mail::fake();
        $restaurant = $this->restaurant();
        $queue = app(QueueService::class);
        $first = $queue->createTicket($restaurant, email: 'satu@contoh.test', emailVerified: true);
        $second = $queue->createTicket($restaurant, email: 'dua@contoh.test', emailVerified: true);
        $third = $queue->createTicket($restaurant, email: 'tiga@contoh.test', emailVerified: true);
        $admin = User::factory()->create(['name' => 'Staf Satu']);

        $this->actingAs($admin)->post(route('admin.restaurants.queue.call-next', $restaurant));
        $this->actingAs($admin)->post(route('admin.restaurants.queue.complete', $restaurant));
        $this->actingAs($admin)->post(route('admin.restaurants.queue.call-next', $restaurant));
        $this->actingAs($admin)->post(route('admin.restaurants.queue.skip', $restaurant));
        $this->actingAs($admin)->post(route('admin.restaurants.queue.cancel', [$restaurant, $third]));

        $this->assertEquals(
            ['call-next', 'complete', 'call-next', 'skip', 'cancel'],
            StaffAction::query()->orderBy('id')->pluck('action')->all()
        );
        $this->assertSame($admin->id, StaffAction::query()->value('user_id'));

        $this->actingAs($admin)->get(route('admin.restaurants.queue', $restaurant))
            ->assertOk()
            ->assertSee('Jejak aktivitas')
            ->assertSee('Staf Satu')
            ->assertSee('Membatalkan antrean');
    }

    public function test_outlet_setting_changes_are_recorded(): void
    {
        $restaurant = $this->restaurant();
        $admin = User::factory()->create();

        $this->actingAs($admin)->put(route('admin.restaurants.update', $restaurant), [
            'name' => 'Ayam Bakar Nusantara', 'category' => 'Ayam', 'description' => '', 'image' => '',
            'prefix' => 'A', 'average_service_minutes' => 6, 'max_wait_minutes' => 30, 'max_party_size' => 6,
            'active' => 1, 'queue_open' => 1, 'queue_mode' => 'seating',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('staff_actions', ['restaurant_id' => $restaurant->id, 'action' => 'restaurant-update', 'user_id' => $admin->id]);
    }

    public function test_staff_routes_require_a_login(): void
    {
        $restaurant = $this->restaurant();
        $ticket = app(QueueService::class)->createTicket($restaurant, email: 'tamu@contoh.test');

        $this->post(route('admin.restaurants.queue.resend-email', [$restaurant, $ticket]))->assertRedirect(route('admin.login'));
        $this->post(route('admin.restaurants.queue.call-next', $restaurant))->assertRedirect(route('admin.login'));
        $this->assertDatabaseCount('staff_actions', 0);
    }

    // ----- Chaos: verification in the middle of a busy queue ------------------------------

    public function test_random_joins_verifications_and_queue_moves_never_alert_an_unverified_email(): void
    {
        Mail::fake();
        $restaurant = $this->restaurant(['max_party_size' => 6]);
        $queue = app(QueueService::class);
        $counter = 0;
        $emails = [];
        mt_srand(2026);

        for ($step = 0; $step < 150; $step++) {
            try {
                match (mt_rand(0, 6)) {
                    0, 1 => $this->chaosJoin($restaurant, $emails, ++$counter),
                    2 => $this->chaosVerify($restaurant, $emails),
                    3 => $queue->callNext($restaurant),
                    4 => $queue->completeCurrent($restaurant),
                    5 => $queue->skipCurrent($restaurant),
                    default => $this->cancelRandomActive($queue, $restaurant),
                };
            } catch (\Throwable) {
                // Full queues and per-email caps are expected outcomes.
            }

            $this->assertNoAlertReachedAnUnverifiedEmail($restaurant, $step);
        }

        $this->assertGreaterThan(0, Mail::sent(QueueEmail::class)->count(), 'The run should exercise sending.');
    }

    // ----- Helpers ------------------------------------------------------------------------

    private function restaurant(array $overrides = []): Restaurant
    {
        return Restaurant::create(array_merge([
            'name' => 'Ayam Bakar Nusantara', 'slug' => 'ayam-bakar', 'prefix' => 'A', 'category' => 'Ayam',
            'average_service_minutes' => 5, 'max_wait_minutes' => 30, 'max_party_size' => 6, 'active' => true, 'queue_open' => true,
        ], $overrides));
    }

    private function join(Restaurant $restaurant, string $email): void
    {
        $this->post(route('outlets.join.store', $restaurant->slug), [
            'party_size' => 2, 'email' => $email, 'consent' => 1,
        ])->assertSessionHasNoErrors();
    }

    /**
     * The token is only ever in the email. Tests read it from the sent mailable, as the customer would.
     */
    private function tokenFor(string $email): string
    {
        /** @var QueueEmail $mail */
        $mail = Mail::sent(QueueEmail::class, fn (QueueEmail $m) => $m->event === TicketEmailEvent::JOINED && $m->hasTo($email))->last();

        return $mail->verifyToken;
    }

    private function verifyUrl(QueueTicket $ticket, string $token): string
    {
        return route('queue.verify', ['ticket' => $ticket->public_token, 'token' => $token]);
    }

    private function countFor(string $email, string $event): int
    {
        return Mail::sent(QueueEmail::class, fn (QueueEmail $m) => $m->event === $event && $m->hasTo($email))->count();
    }

    /**
     * @param  array<int, string>  $emails
     */
    private function chaosJoin(Restaurant $restaurant, array &$emails, int $n): void
    {
        $email = "chaos{$n}@contoh.test";
        $emails[] = $email;
        $this->join($restaurant, $email);
    }

    /**
     * @param  array<int, string>  $emails
     */
    private function chaosVerify(Restaurant $restaurant, array $emails): void
    {
        foreach ($emails as $email) {
            $ticket = QueueTicket::query()->where('email', $email)->whereNull('email_verified_at')->first();
            if ($ticket && $ticket->isActive()) {
                $this->get($this->verifyUrl($ticket, $this->tokenFor($email)));

                return;
            }
        }
    }

    private function assertNoAlertReachedAnUnverifiedEmail(Restaurant $restaurant, int $step): void
    {
        $unverified = QueueTicket::query()
            ->where('restaurant_id', $restaurant->id)
            ->whereNull('email_verified_at')
            ->whereNotNull('email')
            ->pluck('email');

        foreach ($unverified as $email) {
            $leaked = Mail::sent(QueueEmail::class, fn (QueueEmail $m) => $m->hasTo($email) && $m->event !== TicketEmailEvent::JOINED);
            $this->assertCount(0, $leaked, "Step {$step}: an alert reached {$email} before it was verified.");
        }
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
}

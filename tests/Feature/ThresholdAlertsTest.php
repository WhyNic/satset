<?php

namespace Tests\Feature;

use App\Jobs\SendQueueEmailJob;
use App\Mail\QueueEmail;
use App\Models\QueueTicket;
use App\Models\Restaurant;
use App\Models\TicketEmailEvent;
use App\Services\QueueEmailNotificationService;
use App\Services\QueueService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\Support\FlakyMailTransport;
use Tests\TestCase;
use Throwable;

/**
 * Threshold alerts: "tinggal N" when 2 or 3 people are ahead, "tinggal 1" when exactly 1 is ahead.
 * A customer who joins already inside that window must get the alert at once, and each alert is sent once.
 */
class ThresholdAlertsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['email_alerts.enabled' => true, 'email_alerts.max_active_per_email' => 3]);
    }

    // ----- Joining inside the window ------------------------------------------------------

    public function test_joining_with_three_ahead_gets_the_three_alert_at_once(): void
    {
        Mail::fake();
        $restaurant = $this->restaurant();
        $this->queueWaiting($restaurant, 3);

        $this->join($restaurant, 'tamu@contoh.test');

        $this->assertEquals([TicketEmailEvent::JOINED => 1, TicketEmailEvent::THREE_AHEAD => 1], $this->eventsFor('tamu@contoh.test'));
        $this->assertStringContainsString('Tinggal 3 antrean lagi', $this->renderedText('tamu@contoh.test', TicketEmailEvent::THREE_AHEAD));
    }

    public function test_joining_with_two_ahead_says_two_not_three(): void
    {
        Mail::fake();
        $restaurant = $this->restaurant();
        $this->queueWaiting($restaurant, 2);

        $this->join($restaurant, 'tamu@contoh.test');

        $this->assertEquals([TicketEmailEvent::JOINED => 1, TicketEmailEvent::THREE_AHEAD => 1], $this->eventsFor('tamu@contoh.test'));
        $this->assertStringContainsString('Tinggal 2 antrean lagi', $this->renderedText('tamu@contoh.test', TicketEmailEvent::THREE_AHEAD));
        $this->assertStringNotContainsString('Tinggal 3', $this->renderedText('tamu@contoh.test', TicketEmailEvent::THREE_AHEAD));
    }

    public function test_joining_with_one_ahead_gets_only_the_one_alert(): void
    {
        Mail::fake();
        $restaurant = $this->restaurant();
        $this->queueWaiting($restaurant, 1);

        $this->join($restaurant, 'tamu@contoh.test');

        $this->assertEquals([TicketEmailEvent::JOINED => 1, TicketEmailEvent::ONE_AHEAD => 1], $this->eventsFor('tamu@contoh.test'));
    }

    public function test_joining_at_the_front_gets_no_threshold_alert(): void
    {
        Mail::fake();
        $restaurant = $this->restaurant();

        $this->join($restaurant, 'tamu@contoh.test');

        $this->assertEquals([TicketEmailEvent::JOINED => 1], $this->eventsFor('tamu@contoh.test'));
    }

    // ----- Moving through the queue --------------------------------------------------------

    public function test_five_to_called_sends_three_once_one_once_and_called_once(): void
    {
        Mail::fake();
        $restaurant = $this->restaurant();
        $queue = app(QueueService::class);
        $this->queueWaiting($restaurant, 4);
        $this->join($restaurant, 'c@contoh.test'); // position 5, four ahead: no alert yet

        $this->assertEquals([TicketEmailEvent::JOINED => 1], $this->eventsFor('c@contoh.test'));

        $queue->callNext($restaurant);       // first customer called, C still four ahead
        $queue->completeCurrent($restaurant); // C three ahead
        $queue->callNext($restaurant);
        $queue->completeCurrent($restaurant); // C two ahead: no new alert
        $queue->callNext($restaurant);
        $queue->completeCurrent($restaurant); // C one ahead
        $queue->callNext($restaurant);
        $queue->completeCurrent($restaurant); // C at the front
        $queue->callNext($restaurant);        // C called

        $this->assertEquals([
            TicketEmailEvent::JOINED => 1,
            TicketEmailEvent::THREE_AHEAD => 1,
            TicketEmailEvent::ONE_AHEAD => 1,
            TicketEmailEvent::CALLED => 1,
        ], $this->eventsFor('c@contoh.test'));
    }

    public function test_a_customer_who_joins_three_ahead_is_not_skipped_when_the_queue_moves(): void
    {
        Mail::fake();
        $restaurant = $this->restaurant();
        $queue = app(QueueService::class);
        $this->queueWaiting($restaurant, 3);
        $queue->callNext($restaurant);          // one called, two waiting ahead of the new customer
        $this->join($restaurant, 'tamu@contoh.test'); // three ahead, counting the called one
        $queue->completeCurrent($restaurant);   // two ahead now

        $this->assertSame(1, $this->eventsFor('tamu@contoh.test')[TicketEmailEvent::THREE_AHEAD] ?? 0);
    }

    // ----- Alerts are checked again at send time -------------------------------------------

    public function test_a_queued_three_alert_is_dropped_when_the_count_has_moved_to_one(): void
    {
        Mail::fake();
        Bus::fake();
        $restaurant = $this->restaurant();
        $queue = app(QueueService::class);
        $this->queueWaiting($restaurant, 3);
        $this->join($restaurant, 'tamu@contoh.test');   // joined and three alert queued, count three
        $queue->callNext($restaurant);
        $queue->completeCurrent($restaurant);           // count two
        $queue->callNext($restaurant);
        $queue->completeCurrent($restaurant);           // count one: one alert queued

        $this->runQueued();

        $this->assertEquals([TicketEmailEvent::JOINED => 1, TicketEmailEvent::ONE_AHEAD => 1], $this->eventsFor('tamu@contoh.test'));
    }

    // ----- Chaos ---------------------------------------------------------------------------

    #[TestWith([31337])]
    #[TestWith([7])]
    #[TestWith([424242])]
    public function test_random_joins_and_queue_moves_under_smtp_outages_keep_the_threshold_guarantee(int $seed): void
    {
        Bus::fake();
        FlakyMailTransport::reset();
        $this->useFlakyMailer(40);
        $restaurant = $this->restaurant(['max_party_size' => 6]);
        $queue = app(QueueService::class);
        $notifications = app(QueueEmailNotificationService::class);

        $joined = [];   // ticket id => email
        $counter = 0;
        mt_srand($seed);

        for ($step = 0; $step < 200; $step++) {
            try {
                match (mt_rand(0, 6)) {
                    0, 1, 2 => $this->randomJoin($restaurant, $queue, $notifications, $joined, $counter),
                    3 => $queue->callNext($restaurant),
                    4 => $queue->completeCurrent($restaurant),
                    5 => $queue->skipCurrent($restaurant),
                    default => $this->cancelRandomActive($queue, $restaurant),
                };
            } catch (Throwable) {
                // Full queues and per-email caps are expected outcomes of random operations.
            }
        }

        // Replay every queued job like a worker, retrying after each outage until it settles.
        foreach (Bus::dispatched(SendQueueEmailJob::class) as $job) {
            $this->runUntilSettled($job);
        }

        $this->assertGreaterThan(0, FlakyMailTransport::$failures, 'The run must inject real outages.');

        $delivered = collect(FlakyMailTransport::$delivered);
        $this->assertSame(
            $delivered->count(),
            $delivered->map(fn (array $mail): string => $mail['to'].'|'.$mail['subject'])->unique()->count(),
            'An email was delivered more than once.'
        );

        // Guarantees that hold after every outage has been retried:
        //  - every customer who joined gets the confirmation;
        //  - a customer still waiting inside the window (1 to 3 ahead) has a threshold email;
        //  - a customer who has been called has the called email.
        // A customer who is simply next (0 ahead) has no threshold guarantee: the alert would be wrong by then.
        foreach ($joined as $ticketId => $email) {
            $ticket = QueueTicket::query()->find($ticketId);
            $this->assertTrue(
                $delivered->contains(fn (array $mail): bool => $mail['to'] === $email && str_starts_with($mail['subject'], 'Antrean ')),
                "No confirmation reached {$email} after the outages."
            );
            if (! $ticket) {
                continue;
            }

            if ($ticket->status === QueueTicket::STATUS_WAITING) {
                $ahead = QueueEmailNotificationService::peopleAhead($ticket, $queue);
                if ($ahead >= 1 && $ahead <= 3) {
                    $this->assertTrue(
                        $delivered->contains(fn (array $mail): bool => $mail['to'] === $email
                            && str_starts_with($mail['subject'], 'Tinggal ') && str_contains($mail['subject'], $ticket->queue_number)),
                        "Ticket {$ticket->queue_number} is {$ahead} ahead and has no threshold email."
                    );
                }
            }

            if ($ticket->status === QueueTicket::STATUS_CALLED) {
                $this->assertTrue(
                    $delivered->contains(fn (array $mail): bool => $mail['to'] === $email && str_contains($mail['subject'], 'sudah tiba')),
                    "Called ticket {$ticket->queue_number} has no called email."
                );
            }
        }
    }

    // ----- Helpers -------------------------------------------------------------------------

    private function restaurant(array $overrides = []): Restaurant
    {
        return Restaurant::create(array_merge([
            'name' => 'Ayam Bakar Nusantara', 'slug' => 'ayam-bakar', 'prefix' => 'A', 'category' => 'Ayam',
            'average_service_minutes' => 5, 'max_wait_minutes' => 30, 'max_party_size' => 6, 'active' => true, 'queue_open' => true,
        ], $overrides));
    }

    /**
     * Waiting tickets that joined before the probe (no notifications), so the probe starts from a known queue.
     */
    private function queueWaiting(Restaurant $restaurant, int $count): void
    {
        for ($i = 1; $i <= $count; $i++) {
            app(QueueService::class)->createTicket($restaurant, email: "antre{$i}@contoh.test");
        }
    }

    /**
     * A customer joins the way the join controller does: create the ticket, then send its notifications.
     */
    private function join(Restaurant $restaurant, string $email): QueueTicket
    {
        $ticket = app(QueueService::class)->createTicket($restaurant, email: $email);
        app(QueueEmailNotificationService::class)->joined($ticket);

        return $ticket;
    }

    /**
     * @return array<string, int>
     */
    private function eventsFor(string $email): array
    {
        return Mail::sent(QueueEmail::class, fn (QueueEmail $mail) => $mail->hasTo($email))
            ->map(fn (QueueEmail $mail): string => $mail->event)
            ->countBy()
            ->all();
    }

    private function renderedText(string $email, string $event): string
    {
        /** @var QueueEmail $mail */
        $mail = Mail::sent(QueueEmail::class, fn (QueueEmail $m) => $m->hasTo($email) && $m->event === $event)->first();
        $content = $mail->content();

        return view($content->text, $content->with)->render();
    }

    private function runQueued(): void
    {
        foreach (Bus::dispatched(SendQueueEmailJob::class) as $job) {
            $job->handle();
        }
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

    private function useFlakyMailer(int $failurePercent): void
    {
        Mail::extend('flaky', fn () => new FlakyMailTransport($failurePercent, 20261021));
        config(['mail.default' => 'flaky', 'mail.mailers.flaky' => ['transport' => 'flaky']]);
        Mail::purge();
    }

    /**
     * @param  array<int, string>  $joined
     */
    private function randomJoin(Restaurant $restaurant, QueueService $queue, QueueEmailNotificationService $notifications, array &$joined, int &$counter): void
    {
        $email = 'tamu'.(++$counter).'@chaos.test';
        $ticket = $queue->createTicket($restaurant, partySize: mt_rand(1, 6), email: $email);
        $notifications->joined($ticket);
        $joined[$ticket->id] = $email;
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

<?php

namespace Tests\Feature;

use App\Models\QueueTicket;
use App\Models\Restaurant;
use App\Models\User;
use App\Notifications\QueueAlmostTurnNotification;
use App\Notifications\QueueCalledNotification;
use App\Services\QueuePushNotificationService;
use App\Services\QueueService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class SatSetFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_create_queue_ticket_without_login(): void
    {
        $restaurant = $this->restaurant();

        $response = $this->post(route('queue.store', $restaurant));

        $ticket = QueueTicket::query()->firstOrFail();

        $response->assertRedirect(route('queue.show', ['ticket' => $ticket->public_token]));
        $this->assertSame('A-001', $ticket->queue_number);
        $this->assertNull($ticket->phone_number);
        $this->assertNull($ticket->customer_name);
        $this->assertNotNull($ticket->public_token);
    }

    public function test_queue_number_generated_correctly(): void
    {
        $restaurant = $this->restaurant();
        QueueTicket::query()->create($this->ticketData($restaurant, 1));

        $number = app(QueueService::class)->generateQueueNumber($restaurant);

        $this->assertSame(2, $number['sequence_number']);
        $this->assertSame('A-002', $number['queue_number']);
    }

    public function test_estimated_waiting_time_is_calculated(): void
    {
        $restaurant = $this->restaurant(['average_service_minutes' => 5]);

        QueueTicket::query()->create($this->ticketData($restaurant, 1));
        QueueTicket::query()->create($this->ticketData($restaurant, 2));

        $ticket = QueueTicket::query()->create(
            $this->ticketData($restaurant, 3)
        );

        $this->assertSame(
            10,
            app(QueueService::class)->calculateEstimatedWait($restaurant, $ticket)
        );
    }

    public function test_admin_can_call_next_customer(): void
    {
        $restaurant = $this->restaurant();

        $ticket = QueueTicket::query()->create(
            $this->ticketData($restaurant, 1)
        );

        $admin = User::query()->create([
            'name' => 'Admin',
            'email' => 'admin@satset.local',
            'password' => Hash::make('password'),
        ]);

        $this->actingAs($admin)
            ->post(route('admin.restaurants.queue.call-next', $restaurant))
            ->assertSessionHas('status');

        $this->assertSame(
            QueueTicket::STATUS_CALLED,
            $ticket->fresh()->status
        );
    }

    public function test_admin_restaurant_index_links_to_queue_management(): void
    {
        $restaurant = $this->restaurant();

        $admin = User::query()->create([
            'name' => 'Admin',
            'email' => 'admin@satset.local',
            'password' => Hash::make('password'),
        ]);

        $this->actingAs($admin)
            ->get(route('admin.restaurants.index'))
            ->assertOk()
            ->assertSee('Kelola Antrean')
            ->assertSee(route('admin.restaurants.queue', $restaurant), false);
    }

    public function test_admin_queue_management_shows_push_status_and_call_next_triggers_notifications(): void
    {
        Notification::fake();

        config([
            'webpush.vapid.public_key' => 'public',
            'webpush.vapid.private_key' => 'private',
        ]);

        $restaurant = $this->restaurant();

        $tickets = $this->waitingTickets($restaurant, 4);

        $tickets[1]->updatePushSubscription(
            'https://push.example.test/called-from-admin',
            'p256dh',
            'auth',
            'aes128gcm'
        );

        $tickets[4]->updatePushSubscription(
            'https://push.example.test/three-from-admin',
            'p256dh',
            'auth',
            'aes128gcm'
        );

        $admin = User::query()->create([
            'name' => 'Admin',
            'email' => 'admin@satset.local',
            'password' => Hash::make('password'),
        ]);

        $this->actingAs($admin)
            ->get(route('admin.restaurants.queue', $restaurant))
            ->assertOk()
            ->assertSee($restaurant->name)
            ->assertSee('Status restoran')
            ->assertSee('Panggil Berikutnya')
            ->assertSee('Push Notification')
            ->assertSeeInOrder(['A-004', 'Active']);

        $this->actingAs($admin)
            ->post(route('admin.restaurants.queue.call-next', $restaurant))
            ->assertSessionHas('status');

        $this->assertSame(
            QueueTicket::STATUS_CALLED,
            $tickets[1]->fresh()->status
        );

        $this->assertNotNull(
            $tickets[4]->fresh()->three_ahead_notified_at
        );

        Notification::assertSentTo(
            $tickets[1],
            QueueCalledNotification::class,
            1
        );

        Notification::assertSentTo(
            $tickets[4],
            QueueAlmostTurnNotification::class,
            1
        );
    }

    public function test_push_subscription_can_be_saved_for_public_ticket(): void
    {
        $restaurant = $this->restaurant();

        $ticket = QueueTicket::query()->create(
            $this->ticketData($restaurant, 1)
        );

        $this->postJson(
            route('queue.push-subscription.store', [
                'ticket' => $ticket->public_token,
            ]),
            [
                'endpoint' => 'https://push.example.test/subscription/1',
                'keys' => [
                    'p256dh' => 'test-public-key',
                    'auth' => 'test-auth-token',
                ],
                'contentEncoding' => 'aes128gcm',
            ]
        )->assertOk();

        $this->assertSame(
            1,
            $ticket->fresh()->pushSubscriptions()->count()
        );
    }

    public function test_invalid_public_token_is_rejected_for_push_subscription(): void
    {
        $this->postJson(
            '/queue/not-a-real-token/push-subscription',
            [
                'endpoint' => 'https://push.example.test/subscription/1',
                'keys' => [
                    'p256dh' => 'test-public-key',
                    'auth' => 'test-auth-token',
                ],
                'contentEncoding' => 'aes128gcm',
            ]
        )->assertNotFound();
    }

    public function test_cancelled_customer_is_not_in_active_queue(): void
    {
        $restaurant = $this->restaurant();

        $ticket = QueueTicket::query()->create(
            $this->ticketData($restaurant, 1)
        );

        app(QueueService::class)->cancelTicket($ticket);

        $this->assertSame(
            0,
            app(QueueService::class)->getActiveQueueCount($restaurant)
        );
    }

    public function test_recommendation_prefers_same_category_with_shorter_wait(): void
    {
        $busy = $this->restaurant([
            'name' => 'Ayam Bakar Nusantara',
            'prefix' => 'A',
            'category' => 'Ayam',
        ]);

        $fast = $this->restaurant([
            'name' => 'Ayam Geprek Mantap',
            'prefix' => 'B',
            'category' => 'Ayam',
            'average_service_minutes' => 4,
        ]);

        $other = $this->restaurant([
            'name' => 'Rice Bowl & More',
            'prefix' => 'C',
            'category' => 'Rice Bowl',
        ]);

        for ($i = 1; $i <= 10; $i++) {
            QueueTicket::query()->create(
                $this->ticketData($busy, $i)
            );
        }

        QueueTicket::query()->create(
            $this->ticketData($fast, 1)
        );

        $recommendations = app(QueueService::class)
            ->getRecommendations($busy);

        $this->assertTrue(
            $recommendations->contains($fast)
        );

        $this->assertFalse(
            $recommendations->contains($other)
        );

        $this->assertSame(
            $fast->id,
            $recommendations->first()->id
        );
    }

    public function test_three_ahead_notification_is_only_marked_once(): void
    {
        Notification::fake();

        config([
            'webpush.vapid.public_key' => 'public',
            'webpush.vapid.private_key' => 'private',
        ]);

        $restaurant = $this->restaurant();

        $tickets = $this->waitingTickets($restaurant, 5);

        $target = $tickets[4];

        $target->updatePushSubscription(
            'https://push.example.test/three',
            'p256dh',
            'auth',
            'aes128gcm'
        );

        app(QueueService::class)->callNext($restaurant);

        app(QueuePushNotificationService::class)
            ->notifyWaitingPositions(
                $restaurant,
                app(QueueService::class)
            );

        app(QueuePushNotificationService::class)
            ->notifyWaitingPositions(
                $restaurant,
                app(QueueService::class)
            );

        Notification::assertSentTo(
            $target,
            QueueAlmostTurnNotification::class,
            1
        );

        $this->assertNotNull(
            $target->fresh()->three_ahead_notified_at
        );
    }

    public function test_one_ahead_notification_is_only_marked_once(): void
    {
        Notification::fake();

        config([
            'webpush.vapid.public_key' => 'public',
            'webpush.vapid.private_key' => 'private',
        ]);

        $restaurant = $this->restaurant();

        $tickets = $this->waitingTickets($restaurant, 3);

        $target = $tickets[2];

        $target->updatePushSubscription(
            'https://push.example.test/one',
            'p256dh',
            'auth',
            'aes128gcm'
        );

        app(QueueService::class)->callNext($restaurant);

        app(QueuePushNotificationService::class)
            ->notifyWaitingPositions(
                $restaurant,
                app(QueueService::class)
            );

        app(QueuePushNotificationService::class)
            ->notifyWaitingPositions(
                $restaurant,
                app(QueueService::class)
            );

        Notification::assertSentTo(
            $target,
            QueueAlmostTurnNotification::class,
            1
        );

        $this->assertNotNull(
            $target->fresh()->one_ahead_notified_at
        );
    }

    public function test_called_notification_is_only_marked_once(): void
    {
        Notification::fake();

        config([
            'webpush.vapid.public_key' => 'public',
            'webpush.vapid.private_key' => 'private',
        ]);

        $restaurant = $this->restaurant();

        $ticket = QueueTicket::query()->create(
            $this->ticketData(
                $restaurant,
                1,
                [
                    'status' => QueueTicket::STATUS_CALLED,
                ]
            )
        );

        $ticket->updatePushSubscription(
            'https://push.example.test/called',
            'p256dh',
            'auth',
            'aes128gcm'
        );

        app(QueuePushNotificationService::class)
            ->notifyCalled(
                $ticket->load('restaurant')
            );

        app(QueuePushNotificationService::class)
            ->notifyCalled(
                $ticket->fresh('restaurant')
            );

        Notification::assertSentTo(
            $ticket,
            QueueCalledNotification::class,
            1
        );

        $this->assertNotNull(
            $ticket->fresh()->called_notified_at
        );
    }

    public function test_refresh_active_queue_does_not_create_new_ticket(): void
    {
        $restaurant = $this->restaurant();

        $ticket = QueueTicket::query()->create(
            $this->ticketData($restaurant, 1)
        );

        $this->get(
            route('queue.show', [
                'ticket' => $ticket->public_token,
            ])
        )->assertOk();

        $this->get(
            route('queue.show', [
                'ticket' => $ticket->public_token,
            ])
        )->assertOk();

        $this->assertDatabaseCount('queue_tickets', 1);
    }

    private function restaurant(array $overrides = []): Restaurant
    {
        $prefix = $overrides['prefix'] ?? 'A';

        return Restaurant::query()->create([
            'name' => $overrides['name'] ?? 'Ayam Bakar Nusantara',
            'slug' => strtolower($prefix).'-restaurant',
            'category' => $overrides['category'] ?? 'Ayam',
            'description' => $overrides['description'] ?? 'Ayam bakar favorit.',
            'prefix' => $prefix,
            'average_service_minutes' => $overrides['average_service_minutes'] ?? 5,
            'max_wait_minutes' => $overrides['max_wait_minutes'] ?? 30,
            'active' => $overrides['active'] ?? true,
        ]);
    }

    private function ticketData(
        Restaurant $restaurant,
        int $sequence,
        array $overrides = []
    ): array {
        return [
            'restaurant_id' => $restaurant->id,
            'public_token' => $overrides['public_token']
                ?? (string) \Illuminate\Support\Str::uuid(),
            'customer_name' => $overrides['customer_name']
                ?? "Customer {$sequence}",
            'phone_number' => $overrides['phone_number']
                ?? '6281234567'.str_pad(
                    (string) $sequence,
                    3,
                    '0',
                    STR_PAD_LEFT
                ),
            'queue_number' => sprintf(
                '%s-%03d',
                $restaurant->prefix,
                $sequence
            ),
            'sequence_number' => $sequence,
            'status' => $overrides['status']
                ?? QueueTicket::STATUS_WAITING,
            'joined_at' => now(),
        ];
    }

    /**
     * @return array<int, QueueTicket>
     */
    private function waitingTickets(
        Restaurant $restaurant,
        int $count
    ): array {
        $tickets = [];

        for ($i = 1; $i <= $count; $i++) {
            $tickets[$i] = QueueTicket::query()->create(
                $this->ticketData($restaurant, $i)
            );
        }

        return $tickets;
    }
}
<?php

namespace Tests\Feature;

use App\Models\QueueTicket;
use App\Models\Restaurant;
use App\Services\QueueService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PhoneRetentionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::fake();
        config([
            'whatsapp.enabled' => true, 'whatsapp.access_token' => 'fake-test-token',
            'whatsapp.app_secret' => 'fake-test-secret', 'whatsapp.verify_token' => 'fake-verify',
            'whatsapp.phone_number_id' => '12345', 'whatsapp.phone_number' => '628111111111',
            'whatsapp.business_account_id' => null, 'whatsapp.api_version' => 'v23.0',
        ]);
    }

    public function test_completing_a_ticket_clears_its_phone_number_and_keeps_party_size(): void
    {
        $queue = app(QueueService::class);
        $restaurant = $this->restaurant();
        $ticket = $queue->createTicket($restaurant, null, '081234567890', 3);

        $queue->callNext($restaurant);
        $queue->completeCurrent($restaurant);

        $ticket->refresh();
        $this->assertSame(QueueTicket::STATUS_COMPLETED, $ticket->status);
        $this->assertNull($ticket->phone_number);
        $this->assertSame(3, $ticket->party_size);
    }

    public function test_skipping_a_ticket_clears_its_phone_number(): void
    {
        $queue = app(QueueService::class);
        $restaurant = $this->restaurant();
        $ticket = $queue->createTicket($restaurant, null, '081234567890', 2);

        $queue->callNext($restaurant);
        $queue->skipCurrent($restaurant);

        $ticket->refresh();
        $this->assertSame(QueueTicket::STATUS_SKIPPED, $ticket->status);
        $this->assertNull($ticket->phone_number);
    }

    public function test_cancelling_a_ticket_clears_its_phone_number_after_the_notice_is_recorded(): void
    {
        $queue = app(QueueService::class);
        $restaurant = $this->restaurant();
        $ticket = $queue->createTicket($restaurant, null, '081234567890', 2);

        $queue->cancelTicket($ticket);

        $ticket->refresh();
        $this->assertSame(QueueTicket::STATUS_CANCELLED, $ticket->status);
        $this->assertNull($ticket->phone_number);
        $this->assertDatabaseHas('whatsapp_logs', ['event_key' => "ticket:{$ticket->id}:cancelled"]);
    }

    public function test_masked_phone_number_keeps_only_prefix_and_suffix(): void
    {
        $ticket = new QueueTicket(['phone_number' => '6281234567890']);

        $this->assertSame('6281******890', $ticket->maskedPhoneNumber());
    }

    public function test_masked_phone_number_hides_short_numbers_completely(): void
    {
        $ticket = new QueueTicket(['phone_number' => '12345']);

        $this->assertSame('*****', $ticket->maskedPhoneNumber());
    }

    private function restaurant(array $overrides = []): Restaurant
    {
        return Restaurant::create(array_merge([
            'name' => 'Ayam Bakar Nusantara', 'slug' => 'ayam-bakar', 'prefix' => 'A', 'category' => 'Ayam',
            'average_service_minutes' => 5, 'max_wait_minutes' => 30, 'max_party_size' => 6, 'active' => true, 'queue_open' => true,
        ], $overrides));
    }
}

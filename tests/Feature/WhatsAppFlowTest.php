<?php

namespace Tests\Feature;

use App\Models\QueueTicket;
use App\Models\Restaurant;
use App\Models\User;
use App\Models\WhatsAppConversation;
use App\Models\WhatsappLog;
use App\Services\QueueService;
use App\Services\QueueWhatsAppNotificationService;
use App\Services\WhatsAppService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class WhatsAppFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        config([
            'whatsapp.enabled'=>true, 'whatsapp.access_token'=>'fake-test-token',
            'whatsapp.app_secret'=>'fake-test-secret', 'whatsapp.verify_token'=>'fake-verify',
            'whatsapp.phone_number_id'=>'12345', 'whatsapp.phone_number'=>'628111111111',
            'whatsapp.business_account_id'=>null, 'whatsapp.api_version'=>'v23.0',
        ]);
    }

    public function test_whatsapp_redirect_carries_exact_outlet_context_without_ticket(): void
    {
        $restaurant = $this->restaurant();
        $this->get(route('outlets.whatsapp', $restaurant->slug))
            ->assertRedirect('https://wa.me/628111111111?text=ANTRE%20ayam-bakar');
        $this->assertDatabaseCount('queue_tickets', 0);
    }

    #[TestWith(['081234567890', '6281234567890'])]
    #[TestWith(['+6281234567890', '6281234567890'])]
    #[TestWith(['6281234567890', '6281234567890'])]
    public function test_indonesian_phone_normalization(string $input, string $expected): void
    {
        $this->assertSame($expected, app(QueueService::class)->normalizePhoneNumber($input));
    }

    public function test_full_chatbot_flow_creates_one_group_ticket_only_after_confirmation(): void
    {
        $this->fakeApi();
        $restaurant = $this->restaurant();
        $this->incoming('one', 'ANTRE ayam-bakar')->assertOk();
        $this->assertDatabaseHas('whatsapp_conversations', ['restaurant_id'=>$restaurant->id, 'state'=>'waiting_party_size']);
        $this->assertDatabaseCount('queue_tickets', 0);
        $this->incoming('two', '3')->assertOk();
        $this->assertDatabaseCount('queue_tickets', 0);
        $this->assertDatabaseHas('whatsapp_conversations', ['state'=>'waiting_confirmation', 'party_size'=>3]);
        $this->incoming('three', 'YA')->assertOk();
        $this->assertDatabaseHas('queue_tickets', ['queue_number'=>'A-001','party_size'=>3,'phone_number'=>'6281234567890']);
        $this->assertDatabaseCount('queue_tickets', 1);
        $this->flush();
        Http::assertSent(fn ($request) => $request['type'] === 'text' && str_contains($request['text']['body'], 'Berhasil!') && str_contains($request['text']['body'], 'A-001'));
    }

    public function test_ticket_link_uses_public_app_url_not_webhook_host(): void
    {
        $this->fakeApi();
        config(['app.url' => 'https://queue.example.test']);
        $this->restaurant();
        $this->incoming('one', 'ANTRE ayam-bakar');
        $this->incoming('two', '2');
        $this->incoming('three', 'YA');
        $ticket = QueueTicket::query()->firstOrFail();
        $this->assertDatabaseHas('whatsapp_logs', [
            'direction' => 'outbound',
            'queue_ticket_id' => $ticket->id,
            'event_key' => 'reply:three',
        ]);
        $this->assertStringContainsString(
            'https://queue.example.test/queue/'.$ticket->public_token,
            WhatsappLog::query()->where('event_key', 'reply:three')->value('message'),
        );
    }

    #[TestWith(['0'])]
    #[TestWith(['7'])]
    #[TestWith(['-1'])]
    #[TestWith(['2.5'])]
    #[TestWith(['tiga'])]
    public function test_invalid_party_size_is_rejected(string $size): void
    {
        $this->fakeApi();
        $this->restaurant();
        $this->incoming('entry', 'ANTRE ayam-bakar');
        $this->incoming('size', $size)->assertOk();
        $this->assertDatabaseHas('whatsapp_conversations', ['state'=>'waiting_party_size','party_size'=>null]);
        $this->assertDatabaseCount('queue_tickets', 0);
        $this->assertStringContainsString('1 sampai 6', WhatsappLog::where('event_key', 'reply:size')->value('message'));
    }

    public function test_restaurant_limit_can_exceed_three_and_is_rechecked_at_confirmation(): void
    {
        $this->fakeApi();
        $restaurant = $this->restaurant(['max_party_size'=>8]);
        $this->incoming('entry', 'ANTRE ayam-bakar');
        $this->incoming('size', '8');
        $this->assertDatabaseHas('whatsapp_conversations', ['party_size'=>8,'state'=>'waiting_confirmation']);
        $restaurant->update(['max_party_size'=>4]);
        $this->incoming('confirm', 'YA')->assertOk();
        $this->assertDatabaseCount('queue_tickets', 0);
        $this->assertDatabaseHas('whatsapp_conversations', ['state'=>'waiting_party_size']);
    }

    public function test_duplicate_webhook_and_repeated_confirmation_do_not_create_multiple_tickets(): void
    {
        $this->fakeApi();
        $this->restaurant();
        $this->incoming('entry', 'ANTRE ayam-bakar');
        $this->incoming('entry', 'ANTRE ayam-bakar');
        $this->incoming('size', '2');
        $this->incoming('confirm', 'YA');
        $this->incoming('confirm', 'YA')->assertOk();
        $this->incoming('different-confirm', 'YA')->assertOk();
        $this->incoming('again', 'ANTRE ayam-bakar')->assertOk();
        $this->assertDatabaseCount('queue_tickets', 1);
        $this->assertSame(1, WhatsappLog::where('event_key', 'reply:confirm')->count());
        $this->assertSame(1, WhatsappLog::where('provider_message_id', 'entry')->where('direction','inbound')->count());
    }

    public function test_unknown_context_and_closed_outlet_never_create_tickets(): void
    {
        $this->fakeApi();
        $this->restaurant(['queue_open'=>false]);
        $this->incoming('bad', 'ANTRE invalid');
        $this->incoming('entry', 'ANTRE ayam-bakar');
        $this->incoming('size', '2');
        $this->incoming('confirm', 'YA')->assertOk();
        $this->assertDatabaseCount('queue_tickets', 0);
    }

    public function test_expired_confirmation_requires_new_entry(): void
    {
        $this->fakeApi();
        $this->restaurant();
        $this->incoming('entry', 'ANTRE ayam-bakar');
        $this->incoming('size', '2');
        $this->travel(31)->minutes();
        $this->incoming('confirm', 'YA')->assertOk();
        $this->assertDatabaseCount('queue_tickets', 0);
    }

    public function test_verification_and_signature_are_required(): void
    {
        $this->get('/webhooks/whatsapp?hub_mode=subscribe&hub_verify_token=fake-verify&hub_challenge=123')->assertOk()->assertSee('123');
        $this->get('/webhooks/whatsapp?hub_mode=subscribe&hub_verify_token=wrong&hub_challenge=123')->assertForbidden();
        $this->postJson('/webhooks/whatsapp', ['object'=>'whatsapp_business_account'])->assertForbidden();
        $this->assertDatabaseCount('whatsapp_incoming_messages', 0);
        Http::assertNothingSent();
    }

    public function test_wrong_receiving_phone_id_is_ignored(): void
    {
        config(['whatsapp.phone_number_id'=>'other']);
        $this->incoming('entry', 'ANTRE ayam-bakar')->assertOk();
        $this->assertDatabaseCount('whatsapp_incoming_messages', 0);
        Http::assertNothingSent();
    }

    public function test_three_and_one_ahead_and_called_notifications_are_sent_once(): void
    {
        $this->fakeApi();
        $restaurant = $this->restaurant();
        $queue = app(QueueService::class);
        $tickets = [];
        for ($i=1; $i<=4; $i++) {
            $phone = '628123456780'.$i;
            WhatsAppConversation::create(['phone_number'=>$phone,'last_message_at'=>now()]);
            $tickets[] = $queue->createTicket($restaurant, phone:$phone);
        }
        $queue->callNext($restaurant);
        $notifier = app(QueueWhatsAppNotificationService::class);
        $notifier->notifyCalled($tickets[0]->fresh());
        $notifier->notifyWaitingPositions($restaurant, $queue);
        $notifier->notifyWaitingPositions($restaurant, $queue);
        $this->flush();
        $this->assertNotNull($tickets[0]->fresh()->called_notified_at);
        $this->assertNotNull($tickets[1]->fresh()->one_ahead_notified_at);
        $this->assertNotNull($tickets[3]->fresh()->three_ahead_notified_at);
        $this->assertSame(3, WhatsappLog::where('direction','outbound')->count());
        Http::assertSentCount(3);
    }

    public function test_api_failure_does_not_break_admin_action_and_logs_no_credentials(): void
    {
        Http::fake(['https://graph.facebook.com/*'=>Http::response(['error'=>['message'=>'fake-test-token']], 500)]);
        $restaurant = $this->restaurant();
        WhatsAppConversation::create(['phone_number'=>'6281234567890','last_message_at'=>now()]);
        $ticket = app(QueueService::class)->createTicket($restaurant, phone:'6281234567890');
        $this->actingAs(User::factory()->create())->post(route('admin.restaurants.queue.call-next', $restaurant))->assertRedirect();
        $this->flush();
        $this->assertSame('called', $ticket->fresh()->status);
        $this->assertNull($ticket->fresh()->called_notified_at);
        $this->assertDatabaseHas('whatsapp_logs', ['queue_ticket_id'=>$ticket->id,'status'=>'failed','error_code'=>'http_500']);
        $this->get(route('admin.whatsapp-logs.index'))->assertOk()->assertDontSee('fake-test-token');
        Http::assertSentCount(1);
    }

    public function test_transport_failure_does_not_undo_ticket_confirmation(): void
    {
        Http::fake(['https://graph.facebook.com/*'=>Http::failedConnection()]);
        $this->restaurant();
        $this->incoming('entry', 'ANTRE ayam-bakar');
        $this->incoming('size', '2');
        $this->incoming('confirm', 'YA')->assertOk();
        $this->flush();
        $this->assertDatabaseCount('queue_tickets', 1);
        $this->assertDatabaseHas('whatsapp_logs',['event_key'=>'reply:confirm','status'=>'failed','error_code'=>'transport_or_delivery_error']);
    }

    public function test_notification_outside_customer_window_uses_template(): void
    {
        $this->fakeApi();
        config(['whatsapp.templates.called'=>'queue_called']);
        $restaurant = $this->restaurant();
        $ticket = app(QueueService::class)->createTicket($restaurant, phone:'6281234567890');
        app(QueueService::class)->callNext($restaurant);
        $this->flush();
        Http::assertSent(fn ($request) => $request['type']==='template' && $request['template']['name']==='queue_called'
            && $request['template']['components'][0]['parameters'][0]['text']==='A-001');
        $this->assertNotNull($ticket->fresh()->called_notified_at);
    }

    public function test_no_freeform_message_is_sent_outside_customer_window(): void
    {
        $this->fakeApi();
        config(['whatsapp.templates.called'=>null]);
        $restaurant = $this->restaurant();
        $ticket = app(QueueService::class)->createTicket($restaurant, phone:'6281234567890');
        app(QueueService::class)->callNext($restaurant);
        $this->flush();
        $this->assertDatabaseHas('whatsapp_logs',['queue_ticket_id'=>$ticket->id,'status'=>'failed','error_code'=>'template_required']);
        Http::assertNothingSent();
    }

    public function test_cancelled_notification_is_not_duplicated(): void
    {
        $this->fakeApi();
        $restaurant = $this->restaurant();
        WhatsAppConversation::create(['phone_number'=>'6281234567890','last_message_at'=>now()]);
        $queue = app(QueueService::class);
        $ticket = $queue->createTicket($restaurant, phone:'6281234567890');
        $queue->cancelTicket($ticket);
        $queue->cancelTicket($ticket);
        $this->flush();
        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => str_contains($request['text']['body'], 'dibatalkan'));
    }

    private function incoming(string $id, string $text): TestResponse
    {
        $body = json_encode(['object'=>'whatsapp_business_account','entry'=>[['changes'=>[['value'=>[
            'metadata'=>['phone_number_id'=>'12345'],
            'messages'=>[['id'=>$id,'from'=>'6281234567890','timestamp'=>(string) now()->timestamp,'type'=>'text','text'=>['body'=>$text]]],
        ]]]]]], JSON_THROW_ON_ERROR);

        return $this->call('POST', '/webhooks/whatsapp', [], [], [], [
            'CONTENT_TYPE'=>'application/json',
            'HTTP_X_HUB_SIGNATURE_256'=>'sha256='.hash_hmac('sha256', $body, 'fake-test-secret'),
        ], $body);
    }

    private function fakeApi(): void
    {
        $counter = 0;
        Http::fake(['https://graph.facebook.com/*'=>function () use (&$counter) {
            return Http::response(['messages'=>[['id'=>'out-'.++$counter]]]);
        }]);
    }

    private function flush(): void
    {
        $this->artisan('whatsapp:send-pending')->assertSuccessful();
    }

    private function restaurant(array $overrides=[]): Restaurant
    {
        return Restaurant::create(array_merge([
            'name'=>'Ayam Bakar Nusantara','slug'=>'ayam-bakar','prefix'=>'A','category'=>'Ayam',
            'average_service_minutes'=>5,'max_wait_minutes'=>30,'max_party_size'=>6,'active'=>true,'queue_open'=>true,
        ], $overrides));
    }
}

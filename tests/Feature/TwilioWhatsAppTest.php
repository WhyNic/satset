<?php

namespace Tests\Feature;

use App\Models\QueueTicket;
use App\Models\Restaurant;
use App\Models\User;
use App\Models\WhatsappLog;
use App\Services\QueueService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class TwilioWhatsAppTest extends TestCase
{
    use RefreshDatabase;

    // Split so secret scanners do not mistake this fake SID for a real one.
    private const SID = 'AC'.'00000000000000000000000000000000';
    private const TOKEN = 'fake-twilio-token';

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        config([
            'app.url' => 'https://queue.example.test',
            'whatsapp.enabled' => true,
            'whatsapp.provider' => 'twilio',
            'whatsapp.phone_number' => '+1 415 523 8886',
            'whatsapp.twilio.account_sid' => self::SID,
            'whatsapp.twilio.auth_token' => self::TOKEN,
        ]);
    }

    public function test_outlet_page_links_to_twilio_number(): void
    {
        $restaurant = $this->restaurant();
        $this->get(route('outlets.whatsapp', $restaurant->slug))
            ->assertRedirect('https://wa.me/14155238886?text=ANTRE%20ayam-bakar');
    }

    public function test_full_chatbot_flow_over_twilio(): void
    {
        $this->fakeTwilio();
        $this->restaurant();

        $this->incoming('SM1', 'ANTRE ayam-bakar')->assertOk()->assertHeader('Content-Type', 'text/xml; charset=utf-8');
        $this->incoming('SM2', '2')->assertOk();
        $this->incoming('SM3', 'YA')->assertOk();

        $this->assertDatabaseHas('queue_tickets', ['queue_number' => 'A-001', 'party_size' => 2, 'phone_number' => '6281234567890']);
        Http::assertSent(fn (ClientRequest $request) => $request->url() === 'https://api.twilio.com/2010-04-01/Accounts/'.self::SID.'/Messages.json'
            && $request['From'] === 'whatsapp:+14155238886'
            && $request['To'] === 'whatsapp:+6281234567890'
            && str_contains($request['Body'], 'A-001')
            && $request['StatusCallback'] === 'https://queue.example.test/webhooks/twilio/status'
            && $request->hasHeader('Authorization', 'Basic '.base64_encode(self::SID.':'.self::TOKEN)));
        $this->assertSame(3, WhatsappLog::query()->where('direction', 'outbound')->where('status', 'sent')->count());
    }

    public function test_duplicate_twilio_delivery_is_processed_once(): void
    {
        $this->fakeTwilio();
        $this->restaurant();
        $this->incoming('SM1', 'ANTRE ayam-bakar');
        $this->incoming('SM1', 'ANTRE ayam-bakar');

        $this->assertSame(1, WhatsappLog::query()->where('direction', 'inbound')->count());
        Http::assertSentCount(1);
    }

    public function test_invalid_signature_is_rejected(): void
    {
        Http::fake();
        $this->restaurant();
        $this->post('/webhooks/twilio', $this->params('SM1', 'ANTRE ayam-bakar'), ['X-Twilio-Signature' => 'forged'])
            ->assertForbidden();
        $this->assertDatabaseCount('whatsapp_conversations', 0);
        Http::assertNothingSent();
    }

    public function test_meta_webhook_is_closed_when_twilio_is_active(): void
    {
        $this->postJson('/webhooks/whatsapp', ['object' => 'whatsapp_business_account'])->assertStatus(503);
    }

    public function test_status_callback_moves_message_forward(): void
    {
        $this->fakeTwilio();
        $this->restaurant();
        $this->incoming('SM1', 'ANTRE ayam-bakar');
        $outbound = WhatsappLog::query()->where('direction', 'outbound')->firstOrFail();

        $this->signedPost('/webhooks/twilio/status', ['MessageSid' => $outbound->provider_message_id, 'MessageStatus' => 'delivered'])
            ->assertNoContent();
        $this->assertSame('delivered', $outbound->fresh()->status);

        $this->signedPost('/webhooks/twilio/status', ['MessageSid' => $outbound->provider_message_id, 'MessageStatus' => 'sent']);
        $this->assertSame('delivered', $outbound->fresh()->status);
    }

    public function test_notification_outside_session_uses_content_template(): void
    {
        $this->fakeTwilio();
        config(['whatsapp.templates.called' => 'HX0123456789abcdef0123456789abcdef']);
        $restaurant = $this->restaurant();
        app(QueueService::class)->createTicket($restaurant, phone: '081234567890');

        $this->actingAs(User::factory()->create())->post(route('admin.restaurants.queue.call-next', $restaurant));

        Http::assertSent(fn (ClientRequest $request) => ($request['ContentSid'] ?? null) === 'HX0123456789abcdef0123456789abcdef'
            && json_decode($request['ContentVariables'], true) === ['1' => 'A-001', '2' => 'Ayam Bakar Nusantara']
            && ! isset($request['Body']));
        $this->assertNotNull(QueueTicket::query()->firstOrFail()->called_notified_at);
    }

    public function test_twilio_error_code_is_logged_without_secrets(): void
    {
        Http::fake(['https://api.twilio.com/*' => Http::response(['code' => 63016, 'message' => 'Outside window'], 400)]);
        $this->restaurant();
        $this->incoming('SM1', 'ANTRE ayam-bakar');

        $this->assertDatabaseHas('whatsapp_logs', ['direction' => 'outbound', 'status' => 'failed', 'error_code' => 'http_400_63016']);
        $this->actingAs(User::factory()->create())->get(route('admin.whatsapp-logs.index'))
            ->assertOk()->assertDontSee(self::TOKEN);
    }

    private function incoming(string $sid, string $text): TestResponse
    {
        return $this->signedPost('/webhooks/twilio', $this->params($sid, $text));
    }

    private function params(string $sid, string $text): array
    {
        return [
            'MessageSid' => $sid, 'AccountSid' => self::SID, 'Body' => $text,
            'From' => 'whatsapp:+6281234567890', 'To' => 'whatsapp:+14155238886', 'NumMedia' => '0',
        ];
    }

    private function signedPost(string $path, array $params): TestResponse
    {
        ksort($params, SORT_STRING);
        $data = '';
        foreach ($params as $key => $value) {
            $data .= $key.$value;
        }
        $signature = base64_encode(hash_hmac('sha1', 'https://queue.example.test'.$path.$data, self::TOKEN, true));

        return $this->post($path, $params, ['X-Twilio-Signature' => $signature]);
    }

    private function fakeTwilio(): void
    {
        $counter = 0;
        Http::fake(['https://api.twilio.com/*' => function () use (&$counter) {
            return Http::response(['sid' => 'SMout'.++$counter, 'status' => 'queued'], 201);
        }]);
    }

    private function restaurant(): Restaurant
    {
        return Restaurant::query()->create([
            'name' => 'Ayam Bakar Nusantara', 'slug' => 'ayam-bakar', 'prefix' => 'A', 'category' => 'Ayam',
            'average_service_minutes' => 5, 'max_wait_minutes' => 30, 'max_party_size' => 6, 'active' => true, 'queue_open' => true,
        ]);
    }
}

<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Models\WhatsappLog;
use App\Services\WhatsAppChatbotService;
use App\Services\WhatsAppService;

class WhatsAppWebhookController extends Controller
{
    public function verify(Request $request): Response
    {
        $expected = (string) config('whatsapp.verify_token');
        abort_unless($expected !== '' && $request->query('hub_mode') === 'subscribe'
            && hash_equals($expected, (string) $request->query('hub_verify_token')), 403);

        return response((string) $request->query('hub_challenge'), 200)->header('Content-Type', 'text/plain');
    }

    public function receive(Request $request, WhatsAppChatbotService $chatbot, WhatsAppService $whatsapp): Response
    {
        $secret = (string) config('whatsapp.app_secret');
        abort_unless(config('whatsapp.enabled') && $secret !== '' && filled(config('whatsapp.phone_number_id')), 503);
        abort_if(strlen($request->getContent()) > 1048576, 413);
        $signature = 'sha256='.hash_hmac('sha256', $request->getContent(), $secret);
        abort_unless(hash_equals($signature, (string) $request->header('X-Hub-Signature-256')), 403);
        abort_unless($request->input('object') === 'whatsapp_business_account', 422);

        foreach ($request->input('entry', []) as $entry) {
            if (filled(config('whatsapp.business_account_id')) && (string) ($entry['id'] ?? '') !== (string) config('whatsapp.business_account_id')) {
                continue;
            }
            foreach ($entry['changes'] ?? [] as $change) {
                $value = $change['value'] ?? [];
                if ((string) ($value['metadata']['phone_number_id'] ?? '') !== (string) config('whatsapp.phone_number_id')) {
                    continue;
                }
                foreach ($value['messages'] ?? [] as $message) {
                    $chatbot->receive($message);
                }
                foreach ($value['statuses'] ?? [] as $status) {
                    $delivery = $status['status'] ?? '';
                    $allowedPrevious = match ($delivery) {
                        'delivered' => ['sent'],
                        'read' => ['sent', 'delivered'],
                        'failed' => ['sent'],
                        default => [],
                    };
                    if ($allowedPrevious !== []) {
                        WhatsappLog::query()->where('direction', 'outbound')
                            ->where('provider_message_id', $status['id'] ?? '')
                            ->whereIn('status', $allowedPrevious)->update([
                                'status' => $delivery,
                                'error_code' => $delivery === 'failed' ? 'provider_delivery_failed' : null,
                            ]);
                    }
                }
            }
        }
        $whatsapp->dispatchPending();

        return response('EVENT_RECEIVED', 200);
    }
}

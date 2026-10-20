<?php

namespace App\Http\Controllers;

use App\Services\QueueService;
use App\Services\WhatsAppChatbotService;
use App\Services\WhatsAppService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class TwilioWebhookController extends Controller
{
    public function receive(Request $request, WhatsAppChatbotService $chatbot, WhatsAppService $whatsapp, QueueService $queue): Response
    {
        $this->authorizeTwilio($request, $whatsapp);

        $from = (string) $request->input('From', '');
        $toOurNumber = $queue->normalizePhoneNumber((string) $request->input('To', ''))
            === $queue->normalizePhoneNumber((string) config('whatsapp.phone_number'));

        if (str_starts_with($from, 'whatsapp:') && $toOurNumber) {
            $chatbot->receive([
                'id' => (string) $request->input('MessageSid', ''),
                'from' => $from,
                // Quick-reply buttons send their payload; plain chats send Body.
                'text' => ['body' => (string) ($request->input('ButtonPayload') ?: $request->input('Body', ''))],
            ]);
        }
        $whatsapp->dispatchPending();

        // Empty TwiML: replies are sent through the outbox, not inline.
        return response('<?xml version="1.0" encoding="UTF-8"?><Response></Response>', 200)
            ->header('Content-Type', 'text/xml');
    }

    public function status(Request $request, WhatsAppService $whatsapp): Response
    {
        $this->authorizeTwilio($request, $whatsapp);

        $status = match ((string) $request->input('MessageStatus')) {
            'delivered' => 'delivered',
            'read' => 'read',
            'failed', 'undelivered' => 'failed',
            default => '',
        };
        $whatsapp->applyDeliveryStatus((string) $request->input('MessageSid', ''), $status);

        return response('', 204);
    }

    /**
     * Verify X-Twilio-Signature: base64(HMAC-SHA1(auth token, URL + sorted POST key/value pairs)).
     */
    private function authorizeTwilio(Request $request, WhatsAppService $whatsapp): void
    {
        $token = (string) config('whatsapp.twilio.auth_token');
        abort_unless(config('whatsapp.enabled') && $whatsapp->provider() === 'twilio' && $token !== '', 503);
        abort_if(strlen($request->getContent()) > 65536, 413);

        $params = $request->request->all();
        ksort($params, SORT_STRING);
        $data = '';
        foreach ($params as $key => $value) {
            $data .= $key.(is_array($value) ? implode('', $value) : $value);
        }

        // Twilio signs the public URL it called; behind a tunnel Laravel sees a local one, so accept APP_URL too.
        $path = $request->getRequestUri();
        $candidates = array_unique([
            rtrim((string) config('app.url'), '/').$path,
            $request->fullUrl(),
        ]);
        $signature = (string) $request->header('X-Twilio-Signature');

        foreach ($candidates as $url) {
            if (hash_equals(base64_encode(hash_hmac('sha1', $url.$data, $token, true)), $signature)) {
                return;
            }
        }

        abort(403);
    }
}

<?php

namespace App\Jobs;

use App\Models\WhatsappLog;
use App\Services\WhatsAppService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class DeliverWhatsAppMessage implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 30;

    public function __construct(public int $messageId) {}

    public function handle(WhatsAppService $whatsapp): void
    {
        $message = WhatsappLog::query()->find($this->messageId);
        if ($message) {
            $whatsapp->deliver($message);
        }
    }
}

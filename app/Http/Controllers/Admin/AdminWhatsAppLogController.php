<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;
use App\Models\WhatsappLog;

class AdminWhatsAppLogController extends Controller
{
    public function __invoke(Request $request): View
    {
        $status = $request->string('status')->toString();
        $logs = WhatsappLog::query()->with(['restaurant', 'queueTicket'])
            ->when(in_array($status, ['received', 'pending', 'sending', 'sent', 'delivered', 'read', 'failed'], true),
                fn ($query) => $query->where('status', $status))
            ->latest('id')->paginate(25)->withQueryString();

        return view('admin.whatsapp-logs.index', compact('logs', 'status'));
    }
}

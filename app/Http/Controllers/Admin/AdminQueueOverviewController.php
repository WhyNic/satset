<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\QueueTicket;
use Illuminate\Http\Request;

class AdminQueueOverviewController extends Controller
{
    public function __invoke(Request $request)
    {
        $status = $request->query('status', 'all');
        $search = trim((string) $request->query('q', ''));

        $tickets = QueueTicket::query()
            ->with('restaurant')
            ->when(in_array($status, ['waiting', 'called', 'completed', 'cancelled', 'skipped'], true), function ($query) use ($status): void {
                $query->where('status', $status);
            })
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('queue_number', 'like', "%{$search}%")
                        ->orWhere('customer_name', 'like', "%{$search}%")
                        ->orWhere('phone_number', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->latest('joined_at')
            ->paginate(15)
            ->withQueryString();

        return view('admin.queues.index', compact('tickets', 'status', 'search'));
    }
}

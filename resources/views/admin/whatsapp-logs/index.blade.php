<x-layouts.admin title="WhatsApp Logs | SatSet">
    <section class="admin-page-header"><div><p class="admin-kicker">Komunikasi customer</p><h1>WhatsApp Logs</h1><p>Pesan masuk dan status pengiriman notifikasi.</p></div></section>
    @if(!app(\App\Services\WhatsAppService::class)->ready())<div class="admin-notice">WhatsApp belum aktif atau konfigurasi belum lengkap. Operasional antrean tetap tersedia.</div>@endif
    <section class="admin-panel">
        <form class="admin-filter-bar" method="get"><label for="log-status">Status</label><select id="log-status" name="status"><option value="">Semua status</option>@foreach(['received','pending','sending','sent','delivered','read','failed'] as $value)<option @selected($status === $value) value="{{ $value }}">{{ ucfirst($value) }}</option>@endforeach</select><button class="admin-primary-button">Filter</button></form>
        <div class="admin-table-wrap"><table class="admin-table log-table"><thead><tr><th>Waktu</th><th>Antrean</th><th>Restoran</th><th>WhatsApp</th><th>Arah</th><th>Pesan</th><th>Status</th></tr></thead><tbody>
            @forelse($logs as $log)<tr>
                <td>{{ $log->created_at->format('d M H:i') }}</td><td>{{ $log->queueTicket?->queue_number ?? '-' }}</td><td>{{ $log->restaurant?->name ?? '-' }}</td><td>{{ $log->maskedPhoneNumber() ?? '-' }}</td><td>{{ $log->direction === 'inbound' ? 'Masuk' : 'Keluar' }}</td>
                <td><details><summary>{{ \Illuminate\Support\Str::limit($log->message, 70) }}</summary><p class="log-message">{{ $log->message }}</p></details></td>
                <td><span class="admin-status {{ $log->status === 'failed' ? 'admin-status-cancelled' : 'admin-status-waiting' }}">{{ $log->status }}</span>@if($log->error_code)<small>{{ $log->error_code }}</small>@endif</td>
            </tr>@empty<tr><td colspan="7">Belum ada pesan WhatsApp.</td></tr>@endforelse
        </tbody></table></div><div class="admin-pagination">{{ $logs->links() }}</div>
    </section>
</x-layouts.admin>

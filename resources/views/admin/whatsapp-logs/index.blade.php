@php
    $statusLabels = ['received' => 'Diterima', 'pending' => 'Menunggu kirim', 'sending' => 'Mengirim', 'sent' => 'Terkirim', 'delivered' => 'Sampai', 'read' => 'Dibaca', 'failed' => 'Gagal'];
    $statusColors = ['received' => 'grey', 'pending' => 'amber', 'sending' => 'amber', 'sent' => 'green', 'delivered' => 'green', 'read' => 'green', 'failed' => 'red'];
@endphp
<x-layouts.admin title="Log WhatsApp | SatSet Admin">
    <header class="admin-page-header">
        <div>
            <h1>Log WhatsApp</h1>
            <p>Pesan masuk dari customer dan status notifikasi yang dikirim.</p>
        </div>
    </header>

    @if(!app(\App\Services\WhatsAppService::class)->ready())
        <div class="admin-notice admin-notice-warning">WhatsApp belum aktif atau konfigurasinya belum lengkap, jadi pesan keluar tidak terkirim. Operasional antrean tetap berjalan.</div>
    @endif

    <section class="admin-panel">
        <form class="admin-filter-bar" method="get">
            <label class="sr-only" for="log-status">Status</label>
            <select id="log-status" name="status">
                <option value="">Semua status</option>
                @foreach($statusLabels as $value => $label)
                    <option @selected($status === $value) value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
            <button class="admin-button admin-button-primary" type="submit">Terapkan</button>
        </form>

        <div class="admin-table-wrap">
            <table class="admin-table admin-log-table">
                <thead>
                    <tr>
                        <th>Waktu</th>
                        <th>Arah</th>
                        <th>WhatsApp</th>
                        <th>Antrean</th>
                        <th>Pesan</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($logs as $log)
                        <tr>
                            <td class="admin-nowrap">{{ $log->created_at->format('d M, H:i') }}</td>
                            <td><span @class(['admin-direction', 'admin-direction-in' => $log->direction === 'inbound'])>{{ $log->direction === 'inbound' ? '↓ Masuk' : '↑ Keluar' }}</span></td>
                            <td class="admin-mono">{{ $log->phone_number }}</td>
                            <td>
                                <span class="admin-mono">{{ $log->queueTicket?->queue_number ?? '–' }}</span>
                                @if($log->restaurant)<span class="admin-sub">{{ $log->restaurant->name }}</span>@endif
                            </td>
                            <td class="admin-log-cell">
                                <details class="admin-log-message">
                                    <summary>{{ \Illuminate\Support\Str::limit($log->message, 70) }}</summary>
                                    <p>{{ $log->message }}</p>
                                </details>
                            </td>
                            <td>
                                <span class="admin-badge admin-badge-{{ $statusColors[$log->status] ?? 'grey' }}">{{ $statusLabels[$log->status] ?? $log->status }}</span>
                                @if($log->error_code)<span class="admin-sub">{{ $log->error_code }}</span>@endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="admin-empty">Belum ada pesan WhatsApp.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="admin-pagination">{{ $logs->links() }}</div>
    </section>
</x-layouts.admin>

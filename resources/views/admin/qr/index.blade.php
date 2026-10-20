<x-layouts.admin title="QR Code | SatSet">
    <section class="admin-page-header"><div><p class="admin-kicker">Akses customer</p><h1>QR Code outlet</h1><p>QR mengarah langsung ke kondisi antrean masing-masing outlet.</p></div></section>
    <section class="admin-panel"><div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Outlet</th><th>Alamat antrean</th><th>Status</th><th>Action</th></tr></thead><tbody>
        @forelse($restaurants as $restaurant)<tr><td><strong>{{ $restaurant->name }}</strong><span>{{ $restaurant->category }}</span></td><td class="url-cell">{{ rtrim(config('app.url'), '/').route('outlets.show', $restaurant->slug, false) }}</td><td>{{ $restaurant->active && $restaurant->queue_open ? 'Dibuka' : 'Ditutup' }}</td><td><a class="admin-table-action" href="{{ route('admin.qr.show', $restaurant) }}">Lihat QR</a></td></tr>@empty<tr><td colspan="4">Tambahkan restoran untuk membuat QR.</td></tr>@endforelse
    </tbody></table></div></section>
</x-layouts.admin>

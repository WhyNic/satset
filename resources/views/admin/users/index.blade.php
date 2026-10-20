<x-layouts.admin title="Akun admin | SatSet Admin">
    <header class="admin-page-header">
        <div>
            <h1>Akun admin</h1>
            <p>Setiap kasir sebaiknya punya akun sendiri. Admin outlet hanya bisa mengelola outlet yang dipilihkan untuknya.</p>
        </div>
        <a class="admin-button admin-button-primary" href="{{ route('admin.users.create') }}">Tambah akun</a>
    </header>

    <section class="admin-panel">
        <div class="admin-table-wrap">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Nama</th>
                        <th>Peran</th>
                        <th>Outlet</th>
                        <th>Status</th>
                        <th class="admin-cell-action"><span class="sr-only">Aksi</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($users as $user)
                        <tr @class(['admin-row-muted' => ! $user->active])>
                            <td>
                                <strong>{{ $user->name }}@if(auth()->user()->is($user)) <span class="admin-sub admin-inline">(Anda)</span>@endif</strong>
                                <span class="admin-sub">{{ $user->email }}</span>
                            </td>
                            <td><span @class(['admin-badge', 'admin-badge-dark' => $user->isOwner()])>{{ $user->roleLabel() }}</span></td>
                            <td class="admin-outlet-list">
                                @if ($user->isOwner())
                                    <span class="admin-sub">Semua outlet</span>
                                @else
                                    {{ $user->restaurants->pluck('name')->join(', ') ?: '–' }}
                                @endif
                            </td>
                            <td><span @class(['admin-badge', 'admin-badge-green' => $user->active, 'admin-badge-grey' => ! $user->active])>{{ $user->active ? 'Aktif' : 'Nonaktif' }}</span></td>
                            <td class="admin-cell-action"><a class="admin-button admin-button-small" href="{{ route('admin.users.edit', $user) }}">Edit</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>
</x-layouts.admin>

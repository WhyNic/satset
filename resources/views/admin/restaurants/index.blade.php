<x-layouts.admin title="Kelola Restoran - SatSet">
    <section class="admin-page-header">
        <div>
            <p class="admin-kicker">Restaurant Management</p>
            <h1>Kelola restoran</h1>
            <p>Create, edit, enable, dan disable tenant restoran SatSet.</p>
        </div>
        <a class="admin-primary-button" href="{{ route('admin.restaurants.create') }}">Create Restaurant</a>
    </section>

    <section class="admin-panel">
        <div class="admin-table-wrap">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Nama</th>
                        <th>Category</th>
                        <th>Description</th>
                        <th>Prefix</th>
                        <th>Average Service Time</th>
                        <th>Max Wait Time</th>
                        <th>Active</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($restaurants as $restaurant)
                        <tr>
                            <td><strong>{{ $restaurant->name }}</strong></td>
                            <td>{{ $restaurant->category }}</td>
                            <td>{{ \Illuminate\Support\Str::limit($restaurant->description, 50) }}</td>
                            <td>{{ $restaurant->prefix }}</td>
                            <td>{{ $restaurant->average_service_minutes }} menit</td>
                            <td>{{ $restaurant->max_wait_minutes }} menit</td>
                            <td><span class="admin-status {{ $restaurant->active ? 'admin-status-waiting' : 'admin-status-cancelled' }}">{{ $restaurant->active ? 'Active' : 'Disabled' }}</span></td>
                            <td class="admin-inline-actions">
                                <a class="admin-table-action" href="{{ route('admin.restaurants.queue', $restaurant) }}">Kelola Antrean</a>
                                <a class="admin-table-action" href="{{ route('admin.restaurants.edit', $restaurant) }}">Edit</a>
                                <form method="post" action="{{ route('admin.restaurants.destroy', $restaurant) }}">
                                    @csrf
                                    @method('DELETE')
                                    <button class="admin-danger-link" type="submit">{{ $restaurant->active ? 'Disable' : 'Enable' }}</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>
</x-layouts.admin>

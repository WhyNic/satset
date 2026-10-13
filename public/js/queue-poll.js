const ticket = document.querySelector('[data-status-url]');

if (ticket) {
    const statusUrl = ticket.dataset.statusUrl;
    const statusLabel = document.querySelector('[data-ticket-status]');
    const position = document.querySelector('[data-ticket-position]');
    const before = document.querySelector('[data-ticket-before]');
    const estimate = document.querySelector('[data-ticket-estimate]');
    const progress = document.querySelector('[data-ticket-progress]');
    const labelForStatus = (status, position) => {
        if (status === 'waiting') {
            return position > 0 && position <= 3 ? 'Hampir Giliran' : 'Menunggu';
        }

        const labels = {
            called: 'Dipanggil',
            completed: 'Selesai',
            cancelled: 'Dibatalkan',
            skipped: 'Dilewati',
        };

        return labels[status] || `${status.charAt(0).toUpperCase()}${status.slice(1)}`;
    };

    const render = (data) => {
        if (statusLabel) {
            statusLabel.textContent = `Status: ${labelForStatus(data.status, data.position)}`;
        }
        if (position) {
            position.textContent = `Posisi ${data.position || '-'}`;
        }
        if (before) {
            before.textContent = data.people_before;
        }
        if (estimate) {
            estimate.textContent = data.estimated_wait;
        }
        if (progress) {
            progress.style.width = data.position > 0 ? `${Math.max(10, Math.min(100, 100 - ((data.position - 1) * 12)))}%` : '100%';
        }
        if (data.status === 'called' || data.status === 'cancelled' || data.status === 'completed') {
            window.location.reload();
        }
    };

    window.setInterval(async () => {
        try {
            const response = await fetch(statusUrl, { headers: { Accept: 'application/json' } });
            if (response.ok) {
                render(await response.json());
            }
        } catch (error) {
            console.warn('Queue polling failed', error);
        }
    }, 7000);
}

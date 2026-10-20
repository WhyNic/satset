const ticketPanel = document.querySelector('[data-status-url]');
if (ticketPanel) {
    const labels = {waiting: 'Menunggu', called: 'Giliran Anda', completed: 'Selesai', cancelled: 'Dibatalkan', skipped: 'Dilewati'};
    const pollTicket = async () => {
        if (document.hidden) return;
        try {
            const response = await fetch(ticketPanel.dataset.statusUrl, {headers: {Accept: 'application/json'}, cache: 'no-store'});
            if (!response.ok) throw new Error('Unavailable');
            const data = await response.json();
            document.querySelector('[data-ticket-status]').textContent = labels[data.status] || data.status;
            document.querySelector('[data-ticket-before]').textContent = data.people_before;
            document.querySelector('[data-ticket-estimate]').textContent = data.estimated_wait;
            const active = ['waiting', 'called'].includes(data.status);
            document.querySelector('[data-ticket-cancel]')?.toggleAttribute('hidden', !active);
            document.querySelector('[data-ticket-message]').textContent = data.status === 'called'
                ? 'Giliran Anda sudah tiba. Silakan menuju staf outlet.'
                : active ? (ticketPanel.dataset.notify === 'email' ? 'Tunggu dengan nyaman. Pembaruan antrean dikirim melalui email.' : 'Tunggu dengan nyaman. Simpan halaman ini untuk memantau antrean Anda.') : 'Antrean ini sudah berakhir. Terima kasih sudah menggunakan SatSet.';
            document.querySelector('[data-live-note]').textContent = 'Terakhir diperbarui ' + new Date().toLocaleTimeString('id-ID', {hour: '2-digit', minute: '2-digit'});
        } catch {
            document.querySelector('[data-live-note]').textContent = 'Belum dapat memperbarui antrean. Mencoba kembali...';
        }
    };
    window.setInterval(pollTicket, 10000);
    document.addEventListener('visibilitychange', () => { if (!document.hidden) pollTicket(); });
}

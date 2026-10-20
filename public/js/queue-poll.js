const ticketPanel = document.querySelector('[data-ticket]');
if (ticketPanel) {
    const labels = {waiting: 'Menunggu', called: 'Giliran Anda', completed: 'Selesai', cancelled: 'Dibatalkan', skipped: 'Dilewati'};
    const message = ticketPanel.querySelector('[data-ticket-message]');
    const messages = JSON.parse(message.dataset.messages);
    const liveNote = ticketPanel.querySelector('[data-live-note]');
    const pollTicket = async () => {
        if (document.hidden) return;
        try {
            const response = await fetch(ticketPanel.dataset.statusUrl, {headers: {Accept: 'application/json'}, cache: 'no-store'});
            if (!response.ok) throw new Error('Unavailable');
            const data = await response.json();
            const active = ['waiting', 'called'].includes(data.status);
            ticketPanel.dataset.status = data.status;
            ticketPanel.querySelector('[data-ticket-status]').textContent = labels[data.status] || data.status;
            ticketPanel.querySelector('[data-ticket-before]').textContent = data.people_before;
            ticketPanel.querySelector('[data-ticket-estimate]').textContent = data.estimated_wait;
            ticketPanel.querySelector('[data-ticket-cancel]')?.toggleAttribute('hidden', !active);
            message.textContent = messages[active ? data.status : 'ended'];
            liveNote.textContent = 'Terakhir diperbarui ' + new Date().toLocaleTimeString('id-ID', {hour: '2-digit', minute: '2-digit'});
        } catch {
            liveNote.textContent = 'Belum dapat memperbarui antrean. Mencoba kembali...';
        }
    };
    window.setInterval(pollTicket, 10000);
    document.addEventListener('visibilitychange', () => { if (!document.hidden) pollTicket(); });
}

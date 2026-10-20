document.addEventListener('click', (event) => {
    const button = event.target.closest('[data-back-button]');

    if (!button) {
        return;
    }

    const fallbackUrl = button.dataset.fallbackUrl || '/';

    let referrer = null;
    try {
        referrer = document.referrer ? new URL(document.referrer) : null;
    } catch (error) {
        referrer = null;
    }

    let fallback = '/';
    try {
        fallback = new URL(fallbackUrl, window.location.origin);
    } catch (error) {
        fallback = new URL('/', window.location.origin);
    }

    const cameFromSameSite = referrer && referrer.origin === window.location.origin;
    const cameFromCustomer = cameFromSameSite && !referrer.pathname.startsWith('/admin');
    const fallbackIsCustomer = fallback.origin === window.location.origin && !fallback.pathname.startsWith('/admin');

    if (window.history.length > 1 && cameFromCustomer) {
        window.history.back();
        return;
    }

    window.location.href = fallbackIsCustomer ? fallback.href : '/';
});

// Remove the previous installation's worker without registering a new one.
if ('serviceWorker' in navigator) {
    navigator.serviceWorker.getRegistrations().then(registrations => {
        for (const registration of registrations) {
            const worker = registration.active || registration.waiting || registration.installing;
            if (worker && new URL(worker.scriptURL).pathname === '/sw.js') registration.unregister();
        }
    }).catch(() => {});
}

const outlet = document.querySelector('[data-outlet-status]');
if (outlet) {
    let inFlight = false;
    const refreshOutlet = async () => {
        if (document.hidden || inFlight) return;
        inFlight = true;
        try {
            const response = await fetch(outlet.dataset.outletStatus, {headers: {Accept: 'application/json'}, cache: 'no-store'});
            if (!response.ok) throw new Error('Unavailable');
            const data = await response.json();
            outlet.querySelector('[data-outlet-current]').textContent = data.current_number || 'Belum ada';
            outlet.querySelector('[data-outlet-waiting]').textContent = data.waiting_count;
            outlet.querySelector('[data-outlet-estimate]').textContent = data.estimated_wait;
            const status = outlet.querySelector('[data-outlet-availability]');
            status.textContent = data.available ? 'Antrean sedang dibuka' : 'Antrean sedang ditutup';
            status.classList.toggle('closed', !data.available);
            outlet.querySelector('[data-outlet-join]')?.toggleAttribute('hidden', !data.available);
            outlet.querySelector('[data-outlet-cta]')?.toggleAttribute('hidden', !data.available);
            outlet.querySelector('[data-outlet-closed]')?.toggleAttribute('hidden', data.available);
            outlet.querySelector('[data-live-note]').textContent = 'Diperbarui ' + new Date().toLocaleTimeString('id-ID', {hour: '2-digit', minute: '2-digit'}) + '. Estimasi dapat berubah.';
        } catch {
            outlet.querySelector('[data-live-note]').textContent = 'Belum dapat memperbarui kondisi antrean. Mencoba kembali...';
        } finally {
            inFlight = false;
        }
    };
    window.setInterval(refreshOutlet, 15000);
    document.addEventListener('visibilitychange', () => { if (!document.hidden) refreshOutlet(); });
}

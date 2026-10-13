const pushCard = document.querySelector('[data-push-card]');

const setPushState = (message, enabled = false) => {
    if (!pushCard) {
        return;
    }

    const text = pushCard.querySelector('[data-push-message]');
    const button = pushCard.querySelector('[data-push-enable]');

    if (text) {
        text.textContent = message;
    }

    if (button) {
        button.disabled = enabled;
        button.textContent = enabled ? 'Notifikasi aktif' : 'Aktifkan Notifikasi';
    }

    pushCard.classList.toggle('is-active', enabled);
};

const urlBase64ToUint8Array = (base64String) => {
    const padding = '='.repeat((4 - (base64String.length % 4)) % 4);
    const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
    const rawData = window.atob(base64);
    return Uint8Array.from([...rawData].map((char) => char.charCodeAt(0)));
};

if (pushCard) {
    const button = pushCard.querySelector('[data-push-enable]');

    if (pushCard.dataset.hasSubscription === 'true') {
        pushCard.classList.add('is-active');
    }

    button?.addEventListener('click', async () => {
        const publicKey = pushCard.dataset.vapidPublicKey;
        const subscriptionUrl = pushCard.dataset.subscriptionUrl;

        if (!('serviceWorker' in navigator) || !('PushManager' in window) || !('Notification' in window)) {
            setPushState('Browser ini belum mendukung notifikasi push. Pantau antrean dari halaman ini.');
            return;
        }

        if (!publicKey) {
            setPushState('VAPID public key belum dikonfigurasi. Pantau antrean dari halaman ini.');
            return;
        }

        const permission = await Notification.requestPermission();

        if (permission === 'denied') {
            setPushState('Notifikasi diblokir. Anda tetap dapat memantau antrean dari halaman ini.');
            return;
        }

        if (permission !== 'granted') {
            setPushState('Notifikasi belum aktif. Anda tetap dapat memantau antrean dari halaman ini.');
            return;
        }

        try {
            const registration = await navigator.serviceWorker.register('/sw.js');
            const subscription = await registration.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: urlBase64ToUint8Array(publicKey),
            });

            const payload = subscription.toJSON();
            payload.contentEncoding = (PushManager.supportedContentEncodings || ['aes128gcm'])[0];

            const response = await fetch(subscriptionUrl, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                },
                body: JSON.stringify(payload),
            });

            if (!response.ok) {
                throw new Error('Subscription request failed');
            }

            setPushState('Notifikasi aktif. Anda dapat menutup tab ini; SatSet akan memberi tahu saat giliran mendekat.', true);
        } catch (error) {
            console.warn('Push subscription failed', error);
            setPushState('Notifikasi belum bisa diaktifkan. Pantau antrean dari halaman ini.');
        }
    });
}

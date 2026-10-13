self.addEventListener('push', (event) => {
    const fallback = {
        title: 'SatSet',
        body: 'Ada pembaruan antrean SatSet.',
        data: { url: '/' },
    };

    const payload = event.data ? event.data.json() : fallback;
    const title = payload.title || fallback.title;
    const options = {
        body: payload.body || fallback.body,
        icon: payload.icon || '/icons/icon-192.png',
        badge: payload.badge || '/icons/badge-72.png',
        data: payload.data || fallback.data,
        tag: payload.tag,
        requireInteraction: payload.requireInteraction || false,
    };

    event.waitUntil(self.registration.showNotification(title, options));
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();

    const targetUrl = new URL(event.notification.data?.url || '/', self.location.origin).href;

    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true }).then((clientList) => {
            for (const client of clientList) {
                if (client.url === targetUrl && 'focus' in client) {
                    return client.focus();
                }
            }

            return clients.openWindow(targetUrl);
        })
    );
});

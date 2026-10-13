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

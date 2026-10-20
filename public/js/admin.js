document.querySelector('[data-print-qr]')?.addEventListener('click', () => window.print());
document.querySelector('[data-copy-url]')?.addEventListener('click', async event => {
    const status = document.querySelector('[data-copy-status]');
    try {
        await navigator.clipboard.writeText(event.currentTarget.dataset.copyUrl);
        status.textContent = 'URL tersalin.';
    } catch {
        const input = document.querySelector('.qr-url-label input');
        input.focus();
        input.select();
        status.textContent = 'URL dipilih. Salin dari kolom di bawah.';
    }
});

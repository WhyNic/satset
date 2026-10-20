document.querySelector('[data-print-qr]')?.addEventListener('click', () => window.print());
document.querySelector('[data-copy-url]')?.addEventListener('click', async event => {
    const status = document.querySelector('[data-copy-status]');
    try {
        await navigator.clipboard.writeText(event.currentTarget.dataset.copyUrl);
        status.textContent = 'URL tersalin.';
    } catch {
        const input = document.querySelector('[data-url-input]');
        input.focus();
        input.select();
        status.textContent = 'URL dipilih. Salin dari kolom di bawah.';
    }
});

const userForm = document.querySelector('[data-user-form]');
if (userForm) {
    const picker = userForm.querySelector('[data-outlet-picker]');
    userForm.querySelectorAll('[data-role-input]').forEach(input => input.addEventListener('change', () => {
        picker.hidden = userForm.querySelector('[data-role-input]:checked')?.value === 'owner';
    }));
}

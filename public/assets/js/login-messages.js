(() => {
    const [first, second] = document.querySelectorAll('[data-login-message]');

    for (const dialog of [first, second]) {
        dialog.addEventListener('shown.bs.modal', () => dialog.querySelector('button').focus(), { once: true });
    }

    first.addEventListener('hidden.bs.modal', () => bootstrap.Modal.getOrCreateInstance(second).show(), { once: true });
    bootstrap.Modal.getOrCreateInstance(first).show();
})();

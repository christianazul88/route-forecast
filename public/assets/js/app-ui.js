document.querySelectorAll('[data-password-toggle]').forEach((button) => {
    const input = document.getElementById(button.getAttribute('aria-controls'));
    if (!input) return;
    button.addEventListener('click', () => {
        const show = input.type === 'password';
        input.type = show ? 'text' : 'password';
        button.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
        button.title = show ? 'Hide password' : 'Show password';
        button.querySelector('.bi').className = show ? 'bi bi-eye-slash' : 'bi bi-eye';
    });
});

document.querySelectorAll('[data-audit-event]').forEach((button) => {
    button.addEventListener('click', () => {
        const endpoint = document.querySelector('[data-audit-url]')?.dataset.auditUrl;
        const csrf = document.querySelector('.app-account input[name="csrf_token"]')?.value;
        if (!endpoint || !csrf) return;
        const body = new URLSearchParams({csrf_token: csrf, event: button.dataset.auditEvent});
        if (button.dataset.auditId) body.set('id', button.dataset.auditId);
        fetch(endpoint, {method: 'POST', body, keepalive: true}).catch(() => {});
    }, {capture: true});
});

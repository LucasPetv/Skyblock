document.addEventListener('DOMContentLoaded', () => {
    document.querySelector('#accountForm')?.addEventListener('submit', async (event) => {
        event.preventDefault();
        const form = event.currentTarget;
        const formData = new FormData(form);
        try {
            const payload = await App.request('api/accounts.php', { method: 'POST', body: formData });
            App.toast(payload.message, 'success');
            window.location.href = 'accounts.php';
        } catch (error) {
            App.toast(error.message, 'danger');
        }
    });

    document.querySelectorAll('[data-delete-account]').forEach((button) => {
        button.addEventListener('click', async () => {
            if (!window.confirm('Delete this tracked account?')) return;
            try {
                const payload = await App.request('api/accounts.php', {
                    method: 'DELETE',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: button.dataset.deleteAccount, _token: App.csrfToken() }),
                });
                App.toast(payload.message, 'success');
                button.closest('.account-card')?.remove();
            } catch (error) {
                App.toast(error.message, 'danger');
            }
        });
    });

    document.querySelectorAll('[data-sync-account]').forEach((button) => {
        button.addEventListener('click', async () => {
            button.disabled = true;
            const original = button.textContent;
            button.textContent = 'Syncing…';
            try {
                const payload = await App.request('api/sync.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ account_id: button.dataset.syncAccount, _token: App.csrfToken() }),
                });
                App.toast(payload.message, 'success');
                window.location.reload();
            } catch (error) {
                App.toast(error.message, 'danger');
            } finally {
                button.disabled = false;
                button.textContent = original;
            }
        });
    });
});

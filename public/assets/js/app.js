const App = (() => {
    const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

    const toast = (message, type = 'info') => {
        const host = document.querySelector('#toastHost') || (() => {
            const node = document.createElement('div');
            node.id = 'toastHost';
            node.className = 'toast-host';
            document.body.appendChild(node);
            return node;
        })();

        const item = document.createElement('div');
        item.className = `toast toast-${type}`;
        item.textContent = message;
        host.appendChild(item);
        setTimeout(() => item.remove(), 4000);
    };

    const request = async (url, options = {}) => {
        const headers = new Headers(options.headers || {});
        headers.set('Accept', 'application/json');
        if (!headers.has('X-CSRF-Token')) {
            headers.set('X-CSRF-Token', csrfToken());
        }

        const response = await fetch(url, { ...options, headers });
        let payload = {};
        try {
            payload = await response.json();
        } catch (error) {
            payload = { success: false, message: 'Invalid JSON response.' };
        }

        if (!response.ok || payload.success === false) {
            throw new Error(payload.message || 'Request failed.');
        }

        return payload;
    };

    const bindSelectors = () => {
        const accountSelector = document.querySelector('[data-account-selector]');
        const profileSelector = document.querySelector('[data-profile-selector]');

        if (accountSelector) {
            accountSelector.addEventListener('change', () => {
                const url = new URL(window.location.href);
                if (accountSelector.value) {
                    url.searchParams.set('account_id', accountSelector.value);
                    url.searchParams.delete('profile_id');
                }
                window.location.href = url.toString();
            });
        }

        if (profileSelector) {
            profileSelector.addEventListener('change', () => {
                const url = new URL(window.location.href);
                if (profileSelector.value) {
                    url.searchParams.set('profile_id', profileSelector.value);
                }
                window.location.href = url.toString();
            });
        }
    };

    const bindSidebar = () => {
        const button = document.querySelector('[data-sidebar-toggle]');
        const sidebar = document.querySelector('#sidebar');
        if (!button || !sidebar) return;
        button.addEventListener('click', () => sidebar.classList.toggle('is-open'));
    };

    document.addEventListener('DOMContentLoaded', () => {
        bindSelectors();
        bindSidebar();
    });

    return { csrfToken, toast, request };
})();

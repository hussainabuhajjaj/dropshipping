import axios from 'axios';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

if (csrfToken) {
    window.axios.defaults.headers.common['X-CSRF-TOKEN'] = csrfToken;
}

const readCsrfToken = () =>
    document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

const writeCsrfToken = (token) => {
    if (!token) return;

    const existing = document.querySelector('meta[name="csrf-token"]');
    if (existing) {
        existing.setAttribute('content', token);
    } else {
        const meta = document.createElement('meta');
        meta.setAttribute('name', 'csrf-token');
        meta.setAttribute('content', token);
        document.head.appendChild(meta);
    }

    window.axios.defaults.headers.common['X-CSRF-TOKEN'] = token;
};

let csrfRefreshPromise = null;

const refreshCsrfToken = async () => {
    if (typeof window === 'undefined') return '';

    if (!csrfRefreshPromise) {
        csrfRefreshPromise = fetch(window.location.href, {
            method: 'GET',
            credentials: 'same-origin',
            cache: 'no-store',
            headers: {
                Accept: 'text/html,application/xhtml+xml',
                'X-Requested-With': 'XMLHttpRequest',
            },
        })
            .then((response) => response.text())
            .then((html) => {
                const match = html.match(/<meta\s+name=["']csrf-token["']\s+content=["']([^"']+)["']/i);
                const token = match?.[1] || '';

                if (token) {
                    writeCsrfToken(token);
                }

                return token;
            })
            .finally(() => {
                csrfRefreshPromise = null;
            });
    }

    return csrfRefreshPromise;
};

window.axios.interceptors.response.use(
    (response) => response,
    async (error) => {
        const status = error?.response?.status;
        const originalRequest = error?.config;

        if (
            status !== 419
            || !originalRequest
            || originalRequest.__retriedAfterCsrfRefresh
            || typeof window === 'undefined'
        ) {
            return Promise.reject(error);
        }

        originalRequest.__retriedAfterCsrfRefresh = true;

        try {
            const refreshedToken = (await refreshCsrfToken()) || readCsrfToken();

            if (refreshedToken) {
                originalRequest.headers = {
                    ...(originalRequest.headers || {}),
                    'X-CSRF-TOKEN': refreshedToken,
                };
            }

            return window.axios(originalRequest);
        } catch (refreshError) {
            return Promise.reject(refreshError);
        }
    },
);

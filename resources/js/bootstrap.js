import axios from 'axios';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
window.axios.defaults.headers.common['Accept'] = 'application/json';
window.axios.defaults.withCredentials = true;
window.axios.defaults.xsrfCookieName = 'XSRF-TOKEN';
window.axios.defaults.xsrfHeaderName = 'X-XSRF-TOKEN';

const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
const readXsrfCookie = () => {
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);

    return match?.[1] ? decodeURIComponent(match[1]) : '';
};

if (csrfToken) {
    window.axios.defaults.headers.common['X-CSRF-TOKEN'] = csrfToken;
}

const xsrfToken = readXsrfCookie();
if (xsrfToken) {
    window.axios.defaults.headers.common['X-XSRF-TOKEN'] = xsrfToken;
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

    const cookieToken = readXsrfCookie();
    if (cookieToken) {
        window.axios.defaults.headers.common['X-XSRF-TOKEN'] = cookieToken;
    }
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
                    'X-XSRF-TOKEN': readXsrfCookie(),
                };
            }

            return window.axios(originalRequest);
        } catch (refreshError) {
            return Promise.reject(refreshError);
        }
    },
);

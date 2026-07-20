import '../css/app.css';
import './bootstrap';

import {createInertiaApp, router} from '@inertiajs/vue3';
import {resolvePageComponent} from 'laravel-vite-plugin/inertia-helpers';
import {createApp, h} from 'vue';
import { createPinia } from 'pinia';
import {ZiggyVue} from '../../vendor/tightenco/ziggy';

if ('serviceWorker' in navigator && window.isSecureContext) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js').catch(() => null)
    })
}



const appName = import.meta.env.VITE_APP_NAME || 'Simbazu';
const pinia = createPinia()

const trackPageViewPixels = () => {
    if (typeof window === 'undefined') return;

    if (typeof window.ttq?.page === 'function') {
        window.ttq.page();
    }

    if (typeof window.fbq === 'function') {
        window.fbq('track', 'PageView');
    }
};

router.on('success', () => {
    trackPageViewPixels();
});

// Safari bfcache: force fresh data when page is restored from back/forward cache
window.addEventListener('pageshow', (event) => {
    if (event.persisted) {
        router.reload({ preserveState: false, preserveScroll: true })
    }
});

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.vue`,
            import.meta.glob('./Pages/**/*.vue'),
        ),
    setup({el, App, props, plugin}) {
        return createApp({render: () => h(App, props)})
            .use(plugin)
            .use(pinia)
            .use(ZiggyVue)
            .mount(el);
    },
    progress: {
        color: '#4B5563',
    },
});

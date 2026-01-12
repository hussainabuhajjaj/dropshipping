import '../css/app.css';
import './bootstrap';

import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createApp, h } from 'vue';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';
import Toast from "vue-toastification";
import "vue-toastification/dist/index.css";

const appName = import.meta.env.VITE_APP_NAME || 'Simbazu';

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.vue`,
            import.meta.glob('./Pages/**/*.vue'),
        ),
    setup({ el, App, props, plugin }) {
        return createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(ZiggyVue)
            .use(Toast, {
                transition: 'Vue-Toastification__slideBlurred',
                maxToasts: 20,
                newestOnTop: true,
                position: POSITION.TOP_CENTER,
                timeout: 3000,
                filterToasts: toasts => {
                    const types = {};
                    return toasts.reduce((aggToasts, toast) => {
                        if (!types[toast.type]) {
                            aggToasts.push(toast);
                            types[toast.type] = true;
                        }
                        return aggToasts;
                    }, []);
                }
            })
            .mount(el);
    },
    progress: {
        color: '#4B5563',
    },
});

import '../css/app.css';
import './bootstrap';

import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createApp, createSSRApp, h } from 'vue';
import { i18nVue } from 'laravel-vue-i18n';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';
import { fallbackLang, resolveLangAsync } from './i18n';

const appName = 'İmtahan Platforması';

createInertiaApp({
    title: (title) => (title ? `${title} | ${appName}` : appName),
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.vue`,
            import.meta.glob('./Pages/**/*.vue'),
        ),
    setup({ el, App, props, plugin }) {
        // Serverdə render olunmuş HTML varsa (SSR) hydrate edilir, yoxdursa adi mount
        const create = el.hasChildNodes() ? createSSRApp : createApp;
        const app = create({ render: () => h(App, props) })
            .use(plugin)
            .use(ZiggyVue);

        // Tərcümələr yüklənəndən sonra mount edilir ki, SSR HTML-i ilə uyğunsuzluq olmasın
        app.use(i18nVue, {
            lang: props.initialPage.props.locale || fallbackLang,
            fallbackLang,
            resolve: resolveLangAsync,
            onLoad: () => {
                if (!app._container) {
                    app.mount(el);
                }
            },
        });

        return app;
    },
    progress: {
        color: '#2440A0',
    },
});

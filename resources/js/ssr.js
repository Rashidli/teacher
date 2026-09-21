import { createInertiaApp } from '@inertiajs/vue3';
import createServer from '@inertiajs/vue3/server';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { renderToString } from '@vue/server-renderer';
import { createSSRApp, h } from 'vue';
import { i18nVue } from 'laravel-vue-i18n';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';
import { fallbackLang, resolveLangEager } from './i18n';

const appName = 'İmtahan Platforması';

// Port config/inertia.php (INERTIA_SSR_URL) ilə eyni olmalıdır.
// 13714 eyni serverdəki cvhazirla.az saytının SSR prosesinə aiddir.
const port = Number(process.env.INERTIA_SSR_PORT || 13715);

createServer(
    (page) =>
        createInertiaApp({
            page,
            render: renderToString,
            title: (title) => (title ? `${title} | ${appName}` : appName),
            resolve: (name) =>
                resolvePageComponent(
                    `./Pages/${name}.vue`,
                    import.meta.glob('./Pages/**/*.vue'),
                ),
            setup({ App, props, plugin }) {
                return createSSRApp({ render: () => h(App, props) })
                    .use(plugin)
                    .use(ZiggyVue, {
                        ...page.props.ziggy,
                        location: new URL(page.props.ziggy.location),
                    })
                    // shared: false — hər sorğunun öz dili olur (paralel az/ru sorğuları qarışmır)
                    .use(i18nVue, {
                        lang: page.props.locale || fallbackLang,
                        fallbackLang,
                        resolve: resolveLangEager,
                        shared: false,
                    });
            },
        }),
    { port },
);

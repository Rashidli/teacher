import { computed, inject } from 'vue';
import { usePage } from '@inertiajs/vue3';

const DEFAULT_LOCALE = 'az';

/**
 * Cari dil və dil prefiksli route-lar.
 *
 * İctimai route-lar iki dəfə qeydiyyatdan keçir: "login" (az, /login) və "ru.login" (/ru/login).
 * lroute('login') cari dilə uyğun olanı qaytarır.
 *
 * route() ZiggyVue-nun hər app üçün provide etdiyi funksiyadır (inject), ona görə SSR-də
 * paralel sorğular arasında paylaşılmır. Yalnız setup() daxilində çağırılmalıdır.
 */
export function useLocale() {
    const page = usePage();
    const route = inject('route');

    const locale = computed(() => page.props.locale || DEFAULT_LOCALE);

    function lroute(name, params = {}, absolute = false) {
        const localized = locale.value === DEFAULT_LOCALE ? name : `${locale.value}.${name}`;

        return route(route().has(localized) ? localized : name, params, absolute);
    }

    // Dil dəyişdiricisi: cari səhifənin digər dildəki URL-i (backend hesablayır, hreflang ilə eyni)
    const alternates = computed(() => page.props.seo?.alternates ?? null);

    return { locale, lroute, alternates };
}

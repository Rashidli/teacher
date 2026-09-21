// Tərcümə faylları: lang/{az,ru}.json (laravel-lang) və lang/php_{az,ru}.json
// (lang/{az,ru}/*.php-dən laravel-vue-i18n Vite plagini ilə yaradılır).

export const fallbackLang = 'az';

// Brauzer: yalnız cari dilin faylları ayrıca chunk kimi yüklənir
export function resolveLangAsync(lang) {
    const langs = import.meta.glob('../../lang/*.json');
    const loader = langs[`../../lang/${lang}.json`];

    return loader ? loader() : Promise.resolve({ default: {} });
}

// SSR: Promise olmadan, sinxron (laravel-vue-i18n tələbi)
export function resolveLangEager(lang) {
    const langs = import.meta.glob('../../lang/*.json', { eager: true });

    return langs[`../../lang/${lang}.json`]?.default ?? {};
}

<script setup>
import { Link } from '@inertiajs/vue3';
import { useFeatures } from '@/Composables/useFeatures';
import { useLocale } from '@/Composables/useLocale';

const { teachersEnabled } = useFeatures();
const { locale, lroute, alternates } = useLocale();

const languages = ['az', 'ru'];

// Dil dəyişdiricisi cari səhifənin digər dildəki versiyasına aparır.
// Panellərdə (alternates yoxdur) ana səhifəyə aparır.
const languageUrl = (lang) => alternates.value?.[lang] ?? (lang === 'az' ? '/' : `/${lang}`);
</script>

<template>
    <header class="site-header">
        <div class="wrap header-inner">
            <Link :href="lroute('home')" class="brand" :aria-label="$t('site.header.home_aria')">
                <span class="brand-mark" aria-hidden="true"></span>
                <span class="brand-name">{{ $t('site.brand') }}</span>
            </Link>

            <div class="header-end">
                <!-- Tam səhifə yüklənməsi (adi <a>): <html lang> və tərcümə faylları dillə dəyişir -->
                <nav class="lang" :aria-label="$t('site.language.aria')">
                    <template v-for="(lang, i) in languages" :key="lang">
                        <span v-if="i > 0" class="lang-sep" aria-hidden="true">|</span>
                        <a
                            :href="languageUrl(lang)"
                            :hreflang="lang"
                            :lang="lang"
                            class="lang-link"
                            :class="{ 'lang-link--active': lang === locale }"
                            :aria-current="lang === locale ? 'true' : undefined"
                            :aria-label="$t(`site.language.${lang}`)"
                        >{{ lang.toUpperCase() }}</a>
                    </template>
                </nav>

                <nav class="header-nav" :aria-label="$t('site.header.nav_aria')">
                    <a v-if="teachersEnabled" :href="`${lroute('home')}#repetitorlar`" class="nav-link nav-link--wide">{{ $t('site.header.tutors') }}</a>
                    <Link :href="lroute('login')" class="nav-link">{{ $t('site.header.login') }}</Link>
                    <Link :href="lroute('register')" class="nav-button">{{ $t('site.header.register') }}</Link>
                </nav>
            </div>
        </div>
    </header>
</template>

<style scoped>
.site-header {
    border-bottom: 1px solid var(--ink-red-line);
}

.header-inner {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    min-height: 56px;
}

.brand {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    min-width: 44px;
    min-height: 44px;
    color: var(--graphite);
    text-decoration: none;
}

/* Karandaşla doldurulmuş dairə */
.brand-mark {
    width: 20px;
    height: 20px;
    flex: none;
    border-radius: 50%;
    border: 1.5px solid var(--ink-red);
    background: radial-gradient(circle at 40% 38%, #3a3f46 0 45%, var(--graphite) 70%);
    box-shadow: inset 0 0 0 2px var(--paper);
}

/* Çox dar ekranda (məs. 150% zoom) yalnız nişan qalır, ad aria-label-dədir */
.brand-name {
    display: none;
    line-height: 1.2;
    font-family: var(--font-display);
    font-weight: 600;
    font-size: 1.0625rem;
    letter-spacing: -0.01em;
}

.header-end {
    display: flex;
    align-items: center;
    flex: none;
    gap: 4px;
}

.lang {
    display: flex;
    align-items: center;
}

.lang-link {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 44px;
    min-height: 44px;
    border-radius: 6px;
    color: var(--muted);
    font-size: 0.875rem;
    font-weight: 600;
    letter-spacing: 0.02em;
    text-decoration: none;
}

.lang-link:hover {
    color: var(--pen);
}

/* Çox dar ekranda (<300px) yalnız digər dilə keçid görünür; 300px-dən AZ | RU */
.lang-link--active,
.lang-sep {
    display: none;
}

/* Aktiv dil: karandaşla doldurulmuş kimi alt xətt */
.lang-link--active {
    color: var(--graphite);
    text-decoration: underline;
    text-decoration-color: var(--ink-red);
    text-decoration-thickness: 2px;
    text-underline-offset: 6px;
}

.lang-sep {
    color: var(--ink-red-line);
}

.header-nav {
    display: flex;
    flex: none;
    align-items: center;
    gap: 4px;
}

.nav-link,
.nav-button {
    display: inline-flex;
    align-items: center;
    min-height: 44px;
    padding-inline: 12px;
    font-size: 1rem;
    font-weight: 500;
    text-decoration: none;
    border-radius: 6px;
}

.nav-link {
    color: var(--graphite);
}

.nav-link:hover {
    color: var(--pen);
}

.nav-link--wide,
.nav-button {
    display: none;
}

.nav-button {
    color: var(--pen);
    border: 1.5px solid currentColor;
    margin-left: 4px;
}

.nav-button:hover {
    background: var(--pen);
    border-color: var(--pen);
    color: var(--paper);
}

@media (min-width: 300px) {
    .brand { min-width: 0; }
    .brand-name { display: block; }
    .lang-link--active { display: inline-flex; }
    .lang-sep { display: inline; }
}

@media (min-width: 560px) {
    .nav-button { display: inline-flex; }
}

@media (min-width: 720px) {
    .header-inner { min-height: 64px; }
}

@media (min-width: 800px) {
    .nav-link--wide { display: inline-flex; }
    .brand-name { font-size: 1.1875rem; }
}
</style>

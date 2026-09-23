<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import { useFeatures } from '@/Composables/useFeatures';
import { useLocale } from '@/Composables/useLocale';

const { teachersEnabled } = useFeatures();
const { locale, lroute, alternates } = useLocale();
const page = usePage();

const languages = ['az', 'ru'];

const user = computed(() => page.props.auth?.user ?? null);
const roles = computed(() => user.value?.roles ?? []);
const isAdmin = computed(() => roles.value.includes('admin'));
const isStudent = computed(() => roles.value.includes('student'));

// Uzun ad başlığa sığmır: düymədə inisiallar, yanında (geniş ekranda) ad görünür
const initials = computed(() => {
    const parts = [user.value?.first_name, user.value?.last_name].filter(Boolean);

    if (parts.length === 0) {
        return (user.value?.email ?? '?').slice(0, 2).toUpperCase();
    }

    return parts.map((part) => part.trim().charAt(0).toUpperCase()).join('');
});

const shortName = computed(() => user.value?.first_name || user.value?.name || user.value?.email);

/*
 * Hesab menyusunun bəndləri — həm başlıqda, həm mobil paneldə eyni siyahı.
 * Bəndlər ROLA görə seçilir: şagird bölmələri yalnız şagird rolunda göstərilir, əks halda
 * link 403-ə aparardı. Profil və Çıxış hər hesabda var.
 */
const accountLinks = computed(() => {
    const links = [];

    if (isAdmin.value) {
        links.push({ key: 'admin', href: route('admin.dashboard') });
    }

    if (isStudent.value) {
        links.push(
            { key: 'my_exams', href: route('student.exams.index') },
            { key: 'results', href: route('student.results.index') },
            { key: 'statistics', href: route('student.statistics') },
        );
    }

    links.push({ key: 'profile', href: route('profile.edit') });

    return links;
});

/*
 * Dar ekranda başlıqda yer yoxdur (brend + AZ|RU + hesab onsuz da 44px-lik sahələrdir),
 * ona görə naviqasiya açılan panelə yığılır. ≥560px-dən panel yoxdur, linklər görünür.
 */
const menuOpen = ref(false);
const accountOpen = ref(false);

const closeAll = () => {
    menuOpen.value = false;
    accountOpen.value = false;
};

const onKeydown = (event) => {
    if (event.key === 'Escape') {
        closeAll();
    }
};

// Menyudan kənara klik onu bağlayır
const onPointerDown = (event) => {
    if (accountOpen.value && !event.target.closest('.account')) {
        accountOpen.value = false;
    }
};

onMounted(() => {
    document.addEventListener('keydown', onKeydown);
    document.addEventListener('pointerdown', onPointerDown);
});

onUnmounted(() => {
    document.removeEventListener('keydown', onKeydown);
    document.removeEventListener('pointerdown', onPointerDown);
});

// Səhifə dəyişəndə panellər bağlanır (Inertia komponenti yenidən qurmur)
const stopNavigate = router.on('navigate', closeAll);

onUnmounted(() => stopNavigate());

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
                    <Link :href="lroute('exams.catalog')" class="nav-link nav-link--wide">{{ $t('site.header.exams') }}</Link>
                    <a v-if="teachersEnabled" :href="`${lroute('home')}#repetitorlar`" class="nav-link nav-link--wide">{{ $t('site.header.tutors') }}</a>

                    <!-- Qonaq -->
                    <template v-if="!user">
                        <Link :href="lroute('login')" class="nav-link">{{ $t('site.header.login') }}</Link>
                        <Link :href="lroute('register')" class="nav-button">{{ $t('site.header.register') }}</Link>
                    </template>
                </nav>

                <!-- Daxil olmuş istifadəçi: inisiallı açılan menyu -->
                <div v-if="user" class="account">
                    <button
                        type="button"
                        class="account-toggle"
                        :aria-expanded="accountOpen"
                        aria-controls="account-menu"
                        :aria-label="$t('site.header.account_aria')"
                        @click="accountOpen = !accountOpen"
                    >
                        <span class="account-initials" aria-hidden="true">{{ initials }}</span>
                        <span class="account-name">{{ shortName }}</span>
                    </button>

                    <div v-if="accountOpen" id="account-menu" class="account-menu">
                        <p class="account-head">{{ user.full_name || user.name }}</p>
                        <Link
                            v-for="item in accountLinks"
                            :key="item.key"
                            :href="item.href"
                            class="account-link"
                        >{{ $t(`site.header.${item.key}`) }}</Link>
                        <Link
                            :href="route('logout')"
                            method="post"
                            as="button"
                            type="button"
                            class="account-link account-link--out"
                        >{{ $t('site.header.logout') }}</Link>
                    </div>
                </div>

                <button
                    type="button"
                    class="menu-toggle"
                    :aria-expanded="menuOpen"
                    aria-controls="site-menu"
                    :aria-label="menuOpen ? $t('site.header.menu_close') : $t('site.header.menu_open')"
                    @click="menuOpen = !menuOpen"
                >
                    <span class="menu-bars" aria-hidden="true"></span>
                </button>
            </div>
        </div>

        <!-- Dar ekran menyusu: yalnız <560px-də mövcuddur -->
        <nav v-if="menuOpen" id="site-menu" class="menu" :aria-label="$t('site.header.menu_aria')">
            <div class="wrap menu-inner">
                <Link :href="lroute('exams.catalog')" class="menu-link">{{ $t('site.header.exams') }}</Link>
                <a v-if="teachersEnabled" :href="`${lroute('home')}#repetitorlar`" class="menu-link">{{ $t('site.header.tutors') }}</a>

                <template v-if="user">
                    <p class="menu-head">{{ user.full_name || user.name }}</p>
                    <Link v-for="item in accountLinks" :key="item.key" :href="item.href" class="menu-link">
                        {{ $t(`site.header.${item.key}`) }}
                    </Link>
                    <Link
                        :href="route('logout')"
                        method="post"
                        as="button"
                        type="button"
                        class="menu-link menu-link--out"
                    >{{ $t('site.header.logout') }}</Link>
                </template>
                <template v-else>
                    <Link :href="lroute('login')" class="menu-link">{{ $t('site.header.login') }}</Link>
                    <Link :href="lroute('register')" class="menu-link menu-link--strong">{{ $t('site.header.register') }}</Link>
                </template>
            </div>
        </nav>
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

/*
 * 360px-də başlıqda yer yoxdur: brend (~180px) + AZ|RU (96px) + açar (44px) onsuz da
 * konteynerin (328px) hamısını tutur. Ona görə dar ekranda naviqasiya tamamilə panelə
 * yığılır, ≥560px-dən isə birbaşa başlıqda görünür.
 */
.header-nav {
    display: none;
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

/* --------------------------------------------------------------- hesab menyusu */

.account {
    position: relative;
    display: none;
}

.account-toggle {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    min-height: 44px;
    padding: 6px 10px;
    border: 0;
    border-radius: 999px;
    background: none;
    font: inherit;
    cursor: pointer;
}

.account-toggle:hover {
    background: var(--paper-sunk);
}

.account-initials {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 32px;
    height: 32px;
    flex: none;
    border-radius: 50%;
    background: var(--graphite);
    color: var(--paper);
    font-size: 0.8125rem;
    font-weight: 700;
    letter-spacing: 0.02em;
}

.account-name {
    display: none;
    max-width: 12ch;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    font-size: 0.9375rem;
    font-weight: 600;
    color: var(--graphite);
}

.account-menu {
    position: absolute;
    top: calc(100% + 6px);
    right: 0;
    z-index: 30;
    display: grid;
    min-width: 220px;
    padding: 6px;
    border: 1px solid var(--ink-red-line);
    border-radius: 10px;
    background: var(--paper);
    box-shadow: 0 10px 28px rgba(22, 19, 14, 0.14);
}

.account-head {
    margin: 0;
    padding: 8px 12px 6px;
    border-bottom: 1px dashed var(--ink-red-line);
    font-size: 0.8125rem;
    color: var(--muted);
}

.account-link {
    display: flex;
    align-items: center;
    min-height: 44px;
    padding-inline: 12px;
    border: 0;
    border-radius: 6px;
    background: none;
    font: inherit;
    font-size: 0.9375rem;
    text-align: left;
    color: var(--graphite);
    text-decoration: none;
    cursor: pointer;
}

.account-link:hover {
    background: var(--paper-sunk);
}

.account-link--out {
    margin-top: 4px;
    border-top: 1px dashed var(--ink-red-line);
    border-radius: 0 0 6px 6px;
    color: var(--ink-red);
}

/* ------------------------------------------------------------- mobil menyusu */

/* Hamburger: toxunma sahəsi 44×44 */
.menu-toggle {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 44px;
    height: 44px;
    flex: none;
    border: 0;
    border-radius: 6px;
    background: none;
    cursor: pointer;
}

.menu-bars,
.menu-bars::before,
.menu-bars::after {
    display: block;
    width: 20px;
    height: 2px;
    background: var(--graphite);
    border-radius: 2px;
}

.menu-bars {
    position: relative;
}

.menu-bars::before,
.menu-bars::after {
    content: '';
    position: absolute;
    left: 0;
}

.menu-bars::before { top: -6px; }
.menu-bars::after { top: 6px; }

.menu {
    border-top: 1px solid var(--ink-red-line);
}

.menu-inner {
    display: grid;
    padding-block: 8px 12px;
}

.menu-head {
    margin: 8px 0 2px;
    padding-inline: 4px;
    font-size: 0.8125rem;
    color: var(--muted);
}

.menu-link {
    display: flex;
    align-items: center;
    min-height: 44px;
    padding-inline: 4px;
    border: 0;
    background: none;
    font: inherit;
    font-size: 1rem;
    text-align: left;
    color: var(--graphite);
    text-decoration: none;
    cursor: pointer;
}

.menu-link--strong {
    font-weight: 600;
    color: var(--pen);
}

.menu-link--out {
    color: var(--ink-red);
}

@media (min-width: 300px) {
    .brand { min-width: 0; }
    .brand-name { display: block; }
    .lang-link--active { display: inline-flex; }
    .lang-sep { display: inline; }
}

@media (min-width: 560px) {
    /* Linklər başlığa sığır: panel və açarı lazım deyil */
    .header-nav { display: flex; }
    .account { display: block; }
    .nav-link--wide,
    .nav-button { display: inline-flex; }
    .menu-toggle,
    .menu { display: none; }
}

@media (min-width: 720px) {
    .header-inner { min-height: 64px; }
    .account-name { display: inline; }
}

@media (min-width: 800px) {
    .brand-name { font-size: 1.1875rem; }
}
</style>

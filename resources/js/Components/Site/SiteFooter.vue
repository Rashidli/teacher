<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useFeatures } from '@/Composables/useFeatures';
import { useLocale } from '@/Composables/useLocale';

const { teachersEnabled } = useFeatures();
const { lroute } = useLocale();

// Kök kateqoriyalar paylaşılan props-dan gəlir (HandleInertiaRequests), adlar cari dildədir
const categories = computed(() => usePage().props.categories ?? []);

const year = new Date().getFullYear();
</script>

<template>
    <footer class="site-footer">
        <div class="wrap footer-grid">
            <div class="footer-about">
                <p class="footer-brand">{{ $t('site.brand') }}</p>
                <p class="footer-text">{{ $t('site.footer.about') }}</p>
            </div>

            <nav class="footer-col" aria-labelledby="footer-exams">
                <h2 id="footer-exams" class="footer-heading">{{ $t('site.footer.exams') }}</h2>
                <ul>
                    <li v-for="category in categories" :key="category.path">
                        <Link :href="category.url">{{ category.name }}</Link>
                    </li>
                </ul>
            </nav>

            <nav class="footer-col" aria-labelledby="footer-account">
                <h2 id="footer-account" class="footer-heading">{{ $t('site.footer.account') }}</h2>
                <ul>
                    <li><Link :href="lroute('login')">{{ $t('site.footer.login') }}</Link></li>
                    <li><Link :href="lroute('register')">{{ $t('site.footer.register') }}</Link></li>
                    <template v-if="teachersEnabled">
                        <li><Link :href="route('teacher.login')">{{ $t('site.footer.tutor_login') }}</Link></li>
                        <li><Link :href="route('teacher.register')">{{ $t('site.footer.tutor_register') }}</Link></li>
                    </template>
                    <li><Link :href="lroute('terms')">{{ $t('site.footer.terms') }}</Link></li>
                </ul>
            </nav>
        </div>

        <div class="wrap footer-bottom">
            <p>{{ $t('site.footer.copyright', { year }) }}</p>
            <p>{{ $t('site.footer.disclaimer') }}</p>
        </div>
    </footer>
</template>

<style scoped>
/* Cavab kartının sonu: qoşa qırmızı xətt */
.site-footer {
    margin-top: 0;
    padding-block: 48px 24px;
    border-top: 3px double var(--ink-red);
    background: var(--paper);
}

.footer-grid {
    display: grid;
    grid-template-columns: minmax(0, 1fr);
    gap: 32px;
}

.footer-brand {
    font-family: var(--font-display);
    font-weight: 600;
    font-size: 1.1875rem;
    margin: 0 0 8px;
}

.footer-text {
    max-width: 38ch;
    margin: 0;
    color: var(--muted);
    font-size: 1rem;
}

.footer-heading {
    margin: 0 0 8px;
    font-family: var(--font-text);
    font-size: 1rem;
    font-weight: 600;
}

.footer-col ul {
    margin: 0;
    padding: 0;
    list-style: none;
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    column-gap: 16px;
}

/* Link bütün sütun enini tutur: qısa adlar ("MİQ") da 44px-dən dar olmur */
.footer-col a {
    display: flex;
    align-items: center;
    min-height: 44px;
    color: var(--graphite);
    text-decoration: none;
    font-size: 1rem;
}

.footer-col a:hover {
    color: var(--pen);
    text-decoration: underline;
    text-underline-offset: 3px;
}

.footer-bottom {
    display: flex;
    flex-wrap: wrap;
    justify-content: space-between;
    gap: 4px 24px;
    margin-top: 32px;
    padding-top: 16px;
    border-top: 1px solid var(--ink-red-line);
    color: var(--muted);
    font-size: 0.875rem;
}

.footer-bottom p {
    margin: 0;
}

@media (min-width: 800px) {
    .footer-grid {
        grid-template-columns: minmax(0, 1.4fr) repeat(2, minmax(0, 1fr));
    }

    .footer-col ul {
        grid-template-columns: minmax(0, 1fr);
    }
}
</style>

<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import SiteHeader from '@/Components/Site/SiteHeader.vue';
import SiteFooter from '@/Components/Site/SiteFooter.vue';
import SeoHead from '@/Components/Site/SeoHead.vue';
import { categories, categoryRoute } from '@/data/categories';
import { useLocale } from '@/Composables/useLocale';

// Müvəqqəti səhifə: kateqoriyanın testləri hələ hazırlanır (testlər açılana qədər noindex).
const props = defineProps({
    slug: { type: String, required: true },
});

const { lroute } = useLocale();
const others = computed(() => categories.filter((c) => c.slug !== props.slug));
</script>

<template>
    <SeoHead :title="$t(`categories.${slug}.title`)" noindex />

    <div class="site">
        <SiteHeader />

        <main class="wrap placeholder">
            <p class="crumb"><Link :href="lroute('home')">{{ $t('category_page.home') }}</Link></p>
            <h1 class="title">{{ $t(`categories.${slug}.title`) }}</h1>
            <p class="lead">{{ $t('category_page.lead') }}</p>
            <div class="actions">
                <Link :href="lroute('register')" class="button">{{ $t('category_page.register') }}</Link>
                <Link :href="lroute('login')" class="link">{{ $t('category_page.login') }}</Link>
            </div>

            <section class="others" aria-labelledby="others-title">
                <h2 id="others-title" class="others-title">{{ $t('category_page.others') }}</h2>
                <ul>
                    <li v-for="item in others" :key="item.slug">
                        <Link :href="lroute(categoryRoute(item))">
                            <span class="name">{{ $t(`categories.${item.slug}.name`) }}</span>
                            <span class="short">{{ $t(`categories.${item.slug}.short`) }}</span>
                        </Link>
                    </li>
                </ul>
            </section>
        </main>

        <SiteFooter />
    </div>
</template>

<style scoped>
.placeholder {
    padding-block: 40px 80px;
}

.crumb {
    margin: 0 0 8px;
    font-size: 1rem;
}

.crumb a {
    display: inline-flex;
    align-items: center;
    min-height: 44px;
    color: var(--muted);
}

.title {
    margin: 0;
    max-width: 20ch;
    font-family: var(--font-display);
    font-weight: 600;
    font-size: clamp(2rem, 1.4rem + 2.6vw, 3rem);
    line-height: 1.15;
    letter-spacing: -0.015em;
}

.lead {
    margin: 20px 0 0;
    max-width: 56ch;
    color: var(--muted);
}

.actions {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 8px 20px;
    margin-top: 28px;
}

.button {
    display: inline-flex;
    align-items: center;
    min-height: 48px;
    padding-inline: 20px;
    border-radius: 6px;
    background: var(--pen);
    color: #fff;
    font-weight: 600;
    text-decoration: none;
}

.button:hover {
    background: var(--pen-deep);
}

.link {
    display: inline-flex;
    align-items: center;
    min-height: 44px;
    color: var(--pen);
    font-weight: 500;
    text-decoration: underline;
    text-underline-offset: 4px;
}

.others {
    margin-top: 64px;
    padding-top: 24px;
    border-top: 1px solid var(--ink-red-line);
}

.others-title {
    margin: 0 0 8px;
    font-family: var(--font-display);
    font-weight: 600;
    font-size: clamp(1.25rem, 1.1rem + 0.6vw, 1.375rem);
}

.others ul {
    margin: 0;
    padding: 0;
    list-style: none;
    display: grid;
    grid-template-columns: minmax(0, 1fr);
    gap: 0 32px;
}

.others li {
    border-bottom: 1px dashed var(--ink-red-line);
}

.others a {
    display: grid;
    min-height: 56px;
    padding-block: 8px;
    align-content: center;
    color: var(--graphite);
    text-decoration: none;
}

.others a:hover .name {
    color: var(--pen);
}

.name {
    font-weight: 600;
}

.short {
    color: var(--muted);
    font-size: 0.875rem;
}

@media (min-width: 720px) {
    .others ul {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}
</style>

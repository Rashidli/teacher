<script setup>
import SiteHeader from '@/Components/Site/SiteHeader.vue';
import SiteFooter from '@/Components/Site/SiteFooter.vue';
import SeoHead from '@/Components/Site/SeoHead.vue';

/**
 * Giriş, qeydiyyat və parol səhifələrinin ümumi görünüşü: landing-dəki "cavab kartı".
 * Bu səhifələr indekslənmir (noindex).
 */
defineProps({
    title: { type: String, required: true },
    label: { type: String, required: true },
    heading: { type: String, required: true },
    lead: { type: String, default: '' },
    wide: { type: Boolean, default: false },
});
</script>

<template>
    <SeoHead :title="title" noindex />

    <div class="site">
        <SiteHeader />

        <main class="wrap auth-page">
            <div class="auth-column" :class="{ 'auth-column--wide': wide }">
                <section class="auth-card" aria-labelledby="auth-heading">
                    <div class="auth-band">
                        <p class="auth-label">{{ label }}</p>
                    </div>

                    <div class="auth-body">
                        <h1 id="auth-heading" class="auth-heading">{{ heading }}</h1>
                        <p v-if="lead" class="auth-lead">{{ lead }}</p>

                        <slot name="status" />
                        <slot />
                    </div>
                </section>

                <div v-if="$slots.after" class="auth-after">
                    <slot name="after" />
                </div>
            </div>
        </main>

        <SiteFooter />
    </div>
</template>

<style scoped>
.auth-page {
    padding-block: 20px 56px;
}

.auth-column {
    width: 100%;
    max-width: 440px;
    margin-inline: auto;
}

.auth-column--wide {
    max-width: 540px;
}

.auth-card {
    background: #fff;
    border: 1.5px solid var(--ink-red);
    border-radius: 6px;
}

/* Kartın başlıq zolağı; solda optik oxuyucu nişanı */
.auth-band {
    position: relative;
    padding: 10px 16px 10px 28px;
    border-bottom: 1.5px solid var(--ink-red);
}

.auth-band::before {
    content: '';
    position: absolute;
    left: 10px;
    top: 50%;
    width: 8px;
    height: 4px;
    margin-top: -2px;
    background: var(--graphite);
}

.auth-label {
    margin: 0;
    color: var(--ink-red);
    font-size: 0.875rem;
    font-weight: 600;
}

.auth-body {
    padding: 20px 16px 24px;
}

.auth-heading {
    margin: 0;
    font-family: var(--font-display);
    font-weight: 600;
    font-size: clamp(1.5rem, 1.25rem + 1.1vw, 2rem);
    line-height: 1.2;
    letter-spacing: -0.015em;
    text-wrap: balance;
}

.auth-lead {
    margin: 8px 0 0;
    color: var(--muted);
    font-size: 1rem;
}

.auth-after {
    margin-top: 16px;
    padding-inline: 4px;
    font-size: 1rem;
}

@media (min-width: 560px) {
    .auth-page {
        padding-block: 48px 80px;
    }

    .auth-body {
        padding: 28px 28px 32px;
    }

    .auth-band {
        padding-left: 28px;
    }
}
</style>

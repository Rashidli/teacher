<script setup>
import SiteHeader from '@/Components/Site/SiteHeader.vue';
import SiteFooter from '@/Components/Site/SiteFooter.vue';
import SeoHead from '@/Components/Site/SeoHead.vue';

/*
 * Şərtlər və qaydalar (/qaydalar, /ru/qaydalar).
 * Məzmun cari dildə props ilə gəlir: lang/{az,ru}/terms.php.
 * DİQQƏT: mətn ŞABLONDUR, dərc etməzdən əvvəl hüquqşünas yoxlamalıdır
 * ([kvadrat mötərizədəki] yerlər doldurulmalı, qanun istinadları tutuşdurulmalıdır).
 */
defineProps({
    content: { type: Object, required: true },
});
</script>

<template>
    <SeoHead :title="content.title" :description="content.meta_description" />

    <div class="site">
        <SiteHeader />

        <main class="wrap terms">
            <article class="doc">
                <header class="doc-head">
                    <h1 class="doc-title">{{ content.title }}</h1>
                    <p class="doc-updated">{{ content.updated }}</p>
                    <p class="doc-intro">{{ content.intro }}</p>
                </header>

                <nav class="toc" aria-labelledby="toc-title">
                    <h2 id="toc-title" class="toc-title">{{ content.toc }}</h2>
                    <ol class="toc-list">
                        <li v-for="section in content.sections" :key="section.id">
                            <a :href="`#${section.id}`">{{ section.title }}</a>
                        </li>
                    </ol>
                </nav>

                <section
                    v-for="(section, index) in content.sections"
                    :id="section.id"
                    :key="section.id"
                    class="doc-section"
                    :aria-labelledby="`${section.id}-title`"
                >
                    <h2 :id="`${section.id}-title`" class="doc-h2">
                        <span class="doc-num" aria-hidden="true">{{ index + 1 }}.</span>
                        {{ section.title }}
                    </h2>
                    <p v-for="(paragraph, i) in section.paragraphs" :key="`p${i}`">{{ paragraph }}</p>
                    <ul v-if="section.items?.length" class="doc-list">
                        <li v-for="(item, i) in section.items" :key="`i${i}`">{{ item }}</li>
                    </ul>
                    <p v-if="section.closing">{{ section.closing }}</p>
                </section>
            </article>
        </main>

        <SiteFooter />
    </div>
</template>

<style scoped>
.terms {
    padding-block: 32px 72px;
}

.doc {
    max-width: 68ch;
    font-size: 1.0625rem;
    line-height: 1.65;
}

.doc-title {
    margin: 0;
    font-family: var(--font-display);
    font-weight: 600;
    font-size: clamp(1.75rem, 1.3rem + 2vw, 2.5rem);
    line-height: 1.15;
    letter-spacing: -0.015em;
    text-wrap: balance;
}

.doc-updated {
    margin: 10px 0 0;
    color: var(--muted);
    font-size: 0.9375rem;
}

.doc-intro {
    margin: 20px 0 0;
}

/* Mündəricat: cavab kartının xətləri kimi */
.toc {
    margin-top: 28px;
    padding: 16px 16px 8px;
    border: 1px solid var(--ink-red-line);
    border-radius: 6px;
    background: #fff;
}

.toc-title {
    margin: 0 0 4px;
    font-size: 1rem;
    font-weight: 600;
}

.toc-list {
    margin: 0;
    padding-left: 1.5em;
    list-style: decimal;
}

.toc-list li::marker {
    color: var(--ink-red);
    font-variant-numeric: tabular-nums;
}

.toc-list a {
    display: inline-flex;
    align-items: center;
    min-height: 44px;
    color: var(--pen);
    text-underline-offset: 4px;
}

.doc-section {
    margin-top: 40px;
    scroll-margin-top: 16px;
}

.doc-h2 {
    display: flex;
    gap: 0.4em;
    margin: 0 0 12px;
    padding-bottom: 8px;
    border-bottom: 1px solid var(--ink-red-line);
    font-family: var(--font-display);
    font-weight: 600;
    font-size: clamp(1.25rem, 1.1rem + 0.7vw, 1.5rem);
    line-height: 1.3;
}

.doc-num {
    color: var(--ink-red);
    font-variant-numeric: tabular-nums;
}

.doc-section p {
    margin: 0 0 12px;
}

.doc-list {
    margin: 0 0 12px;
    padding-left: 1.25em;
    list-style: disc;
}

.doc-list li {
    margin-bottom: 8px;
    padding-left: 4px;
}

.doc-list li::marker {
    color: var(--ink-red);
}

@media (min-width: 960px) {
    .terms {
        padding-block: 56px 96px;
    }
}
</style>

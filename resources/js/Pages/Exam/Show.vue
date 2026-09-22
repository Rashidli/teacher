<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import SiteHeader from '@/Components/Site/SiteHeader.vue';
import SiteFooter from '@/Components/Site/SiteFooter.vue';
import SeoHead from '@/Components/Site/SeoHead.vue';

defineProps({
    exam: { type: Object, required: true },
    breadcrumb: { type: Array, default: () => [] },
    // { state: 'guest' | 'ready' | 'locked' | 'in_progress', ... }
    status: { type: Object, required: true },
    purchasesEnabled: { type: Boolean, default: false },
    meta: { type: Object, default: () => ({}) },
});

// Cəhd yalnız şagirdin təsdiqi ilə başlayır: qonaq girişdən qayıdanda taymer işə düşmür
const start = useForm({});
const purchase = useForm({});
</script>

<template>
    <SeoHead :title="meta.title" :description="meta.description" />

    <div class="site">
        <SiteHeader />

        <main class="wrap page">
            <nav class="crumb" aria-label="breadcrumb">
                <template v-for="(item, index) in breadcrumb" :key="item.url">
                    <span v-if="index" class="sep" aria-hidden="true">/</span>
                    <Link v-if="index < breadcrumb.length - 1" :href="item.url">{{ item.name }}</Link>
                    <span v-else class="current">{{ item.name }}</span>
                </template>
            </nav>

            <h1 class="title">{{ exam.title }}</h1>
            <p v-if="exam.description" class="intro">{{ exam.description }}</p>

            <!-- Qısa məlumat: müddət, sual sayı, bal, qiymət -->
            <ul class="facts">
                <li>
                    <span class="fact-label">{{ $t('exam_page.duration') }}</span>
                    <span class="fact-value">{{ exam.duration_minutes }} {{ $t('exam_page.minutes') }}</span>
                </li>
                <li>
                    <span class="fact-label">{{ $t('exam_page.question_count') }}</span>
                    <span class="fact-value">{{ exam.question_count }}</span>
                </li>
                <li v-if="exam.max_score">
                    <span class="fact-label">{{ $t('exam_page.max_score') }}</span>
                    <span class="fact-value">{{ exam.max_score }}</span>
                </li>
                <li v-if="exam.kind">
                    <span class="fact-label">{{ $t('exam_page.kind') }}</span>
                    <span class="fact-value">
                        {{ $t(`exam_page.kinds.${exam.kind}`) }}
                        <template v-if="exam.quarter">
                            · {{ $t('exam_page.quarter', { number: exam.quarter }) }}
                        </template>
                    </span>
                </li>
                <li>
                    <span class="fact-label">{{ $t('exam_page.price') }}</span>
                    <span class="fact-value">
                        {{ exam.is_free ? $t('exam_page.free') : `${exam.price} AZN` }}
                    </span>
                </li>
            </ul>

            <!-- Düymə: vəziyyətə görə dəyişir -->
            <section class="cta" aria-labelledby="cta-title">
                <h2 id="cta-title" class="visually-hidden">{{ $t('exam_page.start') }}</h2>

                <template v-if="status.state === 'in_progress'">
                    <Link :href="status.attemptUrl" class="button">{{ $t('exam_page.continue') }}</Link>
                    <p class="cta-hint">
                        {{ $t('exam_page.continue_hint', { minutes: status.remaining_minutes }) }}
                    </p>
                </template>

                <template v-else-if="status.state === 'guest'">
                    <Link :href="status.enterUrl" class="button">
                        {{ exam.is_free ? $t('exam_page.start') : $t('exam_page.buy') }}
                    </Link>
                    <p class="cta-hint">{{ $t('exam_page.guest_hint') }}</p>
                </template>

                <template v-else-if="status.state === 'ready'">
                    <button
                        type="button"
                        class="button"
                        :disabled="start.processing"
                        @click="start.post(status.startUrl)"
                    >{{ $t('exam_page.start') }}</button>
                    <p class="cta-hint">{{ $t('exam_page.start_hint') }}</p>
                </template>

                <template v-else>
                    <button
                        v-if="purchasesEnabled"
                        type="button"
                        class="button"
                        :disabled="purchase.processing"
                        @click="purchase.post(status.purchaseUrl)"
                    >{{ $t('exam_page.buy') }} · {{ exam.price }} AZN</button>
                    <p class="cta-hint">
                        {{ purchasesEnabled ? $t('exam_page.buy_hint') : $t('exam_page.purchases_closed') }}
                    </p>
                </template>

                <Link v-if="status.lastResultUrl" :href="status.lastResultUrl" class="link">
                    {{ $t('exam_page.last_result') }}
                </Link>
            </section>

            <!-- Bölmələr: hansı fənndən neçə sual -->
            <section v-if="exam.sections.length" class="block" aria-labelledby="sections-title">
                <h2 id="sections-title" class="block-title">{{ $t('exam_page.sections') }}</h2>
                <ul class="subjects">
                    <li v-for="(section, index) in exam.sections" :key="index">
                        <span class="subject-name">{{ section.subject || section.title }}</span>
                        <span class="subject-meta">
                            {{ section.question_count }} {{ $t('exam_page.questions') }}
                            <template v-if="section.max_score">
                                · {{ $t('exam_page.max_score') }}: {{ section.max_score }}
                            </template>
                        </span>
                    </li>
                </ul>
            </section>
        </main>

        <SiteFooter />
    </div>
</template>

<style scoped>
.page {
    padding-block: 32px 72px;
}

.crumb {
    font-size: 0.95rem;
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    align-items: center;
}

.crumb .sep {
    opacity: 0.4;
}

.crumb .current {
    opacity: 0.7;
}

.title {
    margin: 12px 0 8px;
}

.intro {
    margin: 0 0 8px;
    max-width: 65ch;
    opacity: 0.85;
}

.facts {
    list-style: none;
    margin: 28px 0 0;
    padding: 0;
    display: grid;
    gap: 12px;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
}

.facts li {
    display: flex;
    flex-direction: column;
    gap: 2px;
    padding: 14px 16px;
    border: 1px solid rgba(22, 19, 14, 0.15);
    border-radius: 12px;
}

.fact-label {
    font-size: 0.9rem;
    opacity: 0.7;
}

.fact-value {
    font-weight: 600;
}

.cta {
    margin-top: 32px;
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 12px 16px;
}

.button {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 48px;
    padding: 12px 28px;
    border: 1px solid var(--graphite);
    border-radius: 999px;
    background: var(--graphite);
    color: var(--paper);
    font: inherit;
    font-weight: 600;
    text-decoration: none;
    cursor: pointer;
}

.button:hover {
    background: var(--pen-deep);
    border-color: var(--pen-deep);
}

.button:disabled {
    opacity: 0.6;
    cursor: progress;
}

.link {
    text-underline-offset: 4px;
}

.cta-hint {
    margin: 0;
    flex: 1 1 260px;
    font-size: 0.95rem;
    opacity: 0.75;
}

.block {
    margin-top: 40px;
}

.block-title {
    font-size: 1.15rem;
    margin: 0 0 14px;
}

.subjects {
    list-style: none;
    margin: 0;
    padding: 0;
    display: grid;
    gap: 8px;
}

.subjects li {
    display: flex;
    flex-wrap: wrap;
    justify-content: space-between;
    gap: 8px;
    padding: 10px 14px;
    border: 1px solid rgba(22, 19, 14, 0.12);
    border-radius: 10px;
}

.subject-meta {
    opacity: 0.7;
    font-size: 0.9rem;
}
</style>

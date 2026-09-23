<script setup>
import { computed } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import SiteHeader from '@/Components/Site/SiteHeader.vue';
import SiteFooter from '@/Components/Site/SiteFooter.vue';
import SeoHead from '@/Components/Site/SeoHead.vue';

const props = defineProps({
    exam: { type: Object, required: true },
    breadcrumb: { type: Array, default: () => [] },
    // { state: 'guest' | 'ready' | 'locked' | 'in_progress', ... }
    status: { type: Object, required: true },
    purchasesEnabled: { type: Boolean, default: false },
    // PAYMENT_DRIVER=fake: ödəniş dərhal təsdiqlənir, real pul hərəkət etmir
    paymentsTestMode: { type: Boolean, default: false },
    meta: { type: Object, default: () => ({}) },
});

// Bölmənin rəngi CSS dəyişəninə yazılır — qlobal stil faylına toxunmadan
const color = computed(() => props.exam.trail?.color || 'var(--muted)');

const trailText = computed(
    () => [props.exam.trail?.root, props.exam.trail?.leaf].filter(Boolean).join(' › '),
);

// Cəhd yalnız şagirdin təsdiqi ilə başlayır: qonaq girişdən qayıdanda taymer işə düşmür
const start = useForm({});
const purchase = useForm({});
</script>

<template>
    <SeoHead :title="meta.title" :description="meta.description" />

    <div class="site" :style="{ '--cat': color }">
        <SiteHeader />

        <main class="wrap page">
            <nav class="crumb" aria-label="breadcrumb">
                <template v-for="(item, index) in breadcrumb" :key="item.url">
                    <span v-if="index" class="sep" aria-hidden="true">/</span>
                    <Link v-if="index < breadcrumb.length - 1" :href="item.url">{{ item.name }}</Link>
                    <span v-else class="current">{{ item.name }}</span>
                </template>
            </nav>

            <p v-if="trailText" class="trail">
                <span class="trail-dot" aria-hidden="true"></span>{{ trailText }}
            </p>

            <h1 class="title">{{ exam.title }}</h1>

            <p class="kind">
                <span class="kind-tag" :class="`kind-tag--${exam.kind}`">
                    {{ $t(`exam_page.kinds.${exam.kind}`) }}
                </span>
                <span v-if="exam.quarter" class="kind-quarter">
                    {{ $t('exam_page.quarter', { number: exam.quarter }) }}
                </span>
            </p>

            <p v-if="exam.description" class="intro">{{ exam.description }}</p>

            <div class="panel">
                <!-- Fakt sətri: ikon + mətn (ikon tək göstərici deyil) -->
                <ul class="facts">
                    <li>
                        <svg class="fact-icon" viewBox="0 0 20 20" aria-hidden="true">
                            <circle cx="10" cy="10" r="7.5" fill="none" stroke="currentColor" stroke-width="1.6" />
                            <path d="M10 5.6V10l3 2" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" />
                        </svg>
                        <span class="fact-label">{{ $t('exam_page.duration') }}</span>
                        <span class="fact-value">{{ exam.duration_minutes }} {{ $t('exam_page.minutes') }}</span>
                    </li>
                    <li>
                        <svg class="fact-icon" viewBox="0 0 20 20" aria-hidden="true">
                            <rect x="4" y="2.8" width="12" height="14.4" rx="2" fill="none" stroke="currentColor" stroke-width="1.6" />
                            <path d="M7.2 7h5.6M7.2 10h5.6M7.2 13h3.2" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" />
                        </svg>
                        <span class="fact-label">{{ $t('exam_page.question_count') }}</span>
                        <span class="fact-value">{{ exam.question_count }}</span>
                    </li>
                    <li v-if="exam.max_score">
                        <svg class="fact-icon" viewBox="0 0 20 20" aria-hidden="true">
                            <path d="M10 2.6l2.2 4.5 5 .7-3.6 3.5.9 5-4.5-2.4-4.5 2.4.9-5L2.8 7.8l5-.7z" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round" />
                        </svg>
                        <span class="fact-label">{{ $t('exam_page.max_score') }}</span>
                        <span class="fact-value">{{ exam.max_score }}</span>
                    </li>
                </ul>

                <!-- Qiymət və əsas düymə: vurğulu blok -->
                <section class="cta" aria-labelledby="cta-title">
                    <h2 id="cta-title" class="visually-hidden">{{ $t('exam_page.start') }}</h2>

                    <p class="cta-price">
                        <span v-if="exam.is_free" class="tag tag--free">{{ $t('exam_page.free') }}</span>
                        <span v-else class="cta-amount">{{ exam.price }} <span class="cta-currency">AZN</span></span>
                    </p>

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
                        >{{ $t('exam_page.buy') }}</button>
                        <p class="cta-hint">
                            {{ purchasesEnabled ? $t('exam_page.buy_hint') : $t('exam_page.purchases_closed') }}
                        </p>
                    </template>

                    <!-- Test rejimi: real ödəniş getmir -->
                    <p v-if="paymentsTestMode && !exam.is_free" class="test-mode">
                        <strong>{{ $t('exam_page.test_mode') }}</strong>
                        {{ $t('exam_page.test_mode_hint') }}
                    </p>

                    <Link v-if="status.lastResultUrl" :href="status.lastResultUrl" class="link">
                        {{ $t('exam_page.last_result') }}
                    </Link>
                </section>
            </div>

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
    font-size: 0.9375rem;
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

/* ------------------------------------------------------- başlıq bloku */

.trail {
    display: flex;
    align-items: center;
    gap: 8px;
    margin: 14px 0 0;
    font-size: 0.8125rem;
    font-weight: 600;
    color: var(--cat);
}

.trail-dot {
    width: 8px;
    height: 8px;
    flex: none;
    border-radius: 50%;
    background: var(--cat);
}

.title {
    margin: 6px 0 10px;
    font-size: 1.75rem;
    font-weight: 700;
    letter-spacing: -0.01em;
    line-height: 1.2;
}

.kind {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 8px;
    margin: 0 0 10px;
}

/* Növ etiketi: rəngli, amma mətn də var (rəng tək göstərici deyil) */
.kind-tag {
    display: inline-flex;
    align-items: center;
    padding: 4px 10px;
    border-radius: 999px;
    background: var(--paper-sunk);
    font-size: 0.8125rem;
    font-weight: 600;
}

.kind-tag--general { color: var(--graphite); }
.kind-tag--topic_trial { color: var(--pen); background: #E9ECF6; }
.kind-tag--subject { color: #5A3489; background: #EFEAF5; }
.kind-tag--practice { color: #0F766E; background: #E4EFEC; }

.kind-quarter {
    font-size: 0.9375rem;
    color: var(--muted);
}

.intro {
    margin: 0 0 8px;
    max-width: 65ch;
    color: var(--muted);
}

/* ------------------------------------------------- faktlar + əsas düymə */

.panel {
    display: grid;
    gap: 16px;
    margin-top: 24px;
}

.facts {
    list-style: none;
    margin: 0;
    padding: 0;
    display: grid;
    gap: 10px;
}

.facts li {
    display: flex;
    align-items: center;
    gap: 8px;
    min-width: 0;
}

.fact-icon {
    width: 20px;
    height: 20px;
    flex: none;
    color: var(--cat);
}

.fact-label {
    font-size: 0.9375rem;
    color: var(--muted);
}

.fact-value {
    font-weight: 600;
}

.cta {
    display: grid;
    gap: 10px;
    padding: 18px 16px;
    border: 1px solid rgba(22, 19, 14, 0.12);
    border-radius: 14px;
    background: var(--paper-sunk);
}

.cta-price {
    margin: 0;
}

.cta-amount {
    font-family: var(--font-display);
    font-size: 1.6rem;
    font-weight: 700;
    line-height: 1;
}

.cta-currency {
    font-size: 1rem;
    font-weight: 600;
    color: var(--muted);
}

.tag {
    display: inline-flex;
    align-items: center;
    padding: 5px 12px;
    border-radius: 999px;
    font-size: 0.9375rem;
    font-weight: 700;
}

.tag--free {
    background: #E6EFEA;
    color: var(--correct);
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
    justify-self: start;
    text-underline-offset: 4px;
}

.cta-hint {
    margin: 0;
    min-width: 0;
    font-size: 0.9375rem;
    color: var(--muted);
}

/* Test rejimi xəbərdarlığı: yol nişanı sarısı, mətnlə birlikdə */
.test-mode {
    margin: 0;
    padding: 10px 12px;
    border: 1px solid rgba(180, 130, 10, 0.45);
    border-left: 4px solid var(--sign-yellow);
    border-radius: 8px;
    background: #FBF6E6;
    font-size: 0.9375rem;
    color: #5C4405;
}

.test-mode strong {
    display: block;
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
    min-width: 0;
    padding: 10px 14px;
    border: 1px solid rgba(22, 19, 14, 0.12);
    border-radius: 10px;
}

.subject-name {
    min-width: 0;
}

.subject-meta {
    opacity: 0.7;
    font-size: 0.9375rem;
}

@media (min-width: 640px) {
    /* Müddət, sual sayı və bal bir sətirdə */
    .facts {
        display: flex;
        flex-wrap: wrap;
        gap: 10px 28px;
    }

    .cta-hint,
    .fact-label,
    .subject-meta,
    .crumb { font-size: 0.9rem; }
}

@media (min-width: 900px) {
    /* Faktlar solda, qiymət və düymə sağda vurğulu blokda */
    .panel {
        grid-template-columns: minmax(0, 1fr) 300px;
        align-items: start;
        gap: 28px;
    }

    .facts {
        flex-direction: column;
        gap: 12px;
    }

    .title { font-size: 2rem; }
}
</style>

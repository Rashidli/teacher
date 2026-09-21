<script setup>
import { Link } from '@inertiajs/vue3';
import SiteHeader from '@/Components/Site/SiteHeader.vue';
import SiteFooter from '@/Components/Site/SiteFooter.vue';
import TutorsSection from '@/Components/Site/TutorsSection.vue';
import { useFeatures } from '@/Composables/useFeatures';
import SeoHead from '@/Components/Site/SeoHead.vue';
import { categories, categoryRoute } from '@/data/categories';
import { useLocale } from '@/Composables/useLocale';

const { teachersEnabled } = useFeatures();
const { lroute } = useLocale();

const path = (slug) => lroute(`category.${slug}`);

// Hero-dakı dekorativ "kod" şəbəkəsi: hər sütunda bir xana karandaşla doldurulur.
const codeMarks = [2, 0, 1, 2, 0];
const codeRows = 3;

const mathOptions = [
    { letter: 'A', text: '18' },
    { letter: 'B', text: '24', chosen: true },
    { letter: 'C', text: '30' },
    { letter: 'D', text: '36' },
    { letter: 'E', text: '48' },
];

// Mətnlər: landing.sample.drive.options.{0,1,2}
const driveOptions = ['1', '2', '3'];

// Nəticə nümunəsi: 25 sual, 18 düzgün cavab.
const topics = [
    { key: 'percent', correct: 5, total: 5 },
    { key: 'equations', correct: 6, total: 7 },
    { key: 'functions', correct: 4, total: 5 },
    { key: 'geometry', correct: 2, total: 5 },
    { key: 'logarithm', correct: 1, total: 3 },
].map((t) => ({ ...t, percent: Math.round((t.correct / t.total) * 100), weak: t.correct / t.total < 0.5 }));
</script>

<template>
    <SeoHead />

    <div class="site">
        <SiteHeader />

        <main>
            <!-- HERO -->
            <section class="hero" aria-labelledby="hero-title">
                <!-- Telefonda kart başlıqdan dərhal sonra gəlir ki, 6 dairə ilk ekrana sığsın -->
                <div class="wrap hero-grid">
                    <h1 id="hero-title" class="hero-title">{{ $t('landing.hero.title') }}</h1>

                    <div class="answer-card">
                        <div class="card-head">
                            <p class="card-label">{{ $t('landing.card.label') }}</p>
                            <div class="code" aria-hidden="true">
                                <span class="code-label">{{ $t('landing.card.code') }}</span>
                                <span class="code-grid">
                                    <span v-for="(mark, col) in codeMarks" :key="col" class="code-col">
                                        <span
                                            v-for="row in codeRows"
                                            :key="row"
                                            class="code-cell"
                                            :class="{ 'code-cell--mark': row - 1 === mark }"
                                            :style="{ '--i': col }"
                                        ></span>
                                    </span>
                                </span>
                            </div>
                        </div>

                        <p id="hero-question" class="card-question">{{ $t('landing.card.question') }}</p>
                        <p class="card-hint">{{ $t('landing.card.hint') }}</p>

                        <nav aria-labelledby="hero-question">
                            <ul class="choices">
                                <li v-for="category in categories" :key="category.slug">
                                    <Link :href="lroute(categoryRoute(category))" class="choice">
                                        <span class="bubble" aria-hidden="true">{{ category.letter }}</span>
                                        <span class="choice-text">
                                            <span class="choice-name">{{ $t(`categories.${category.slug}.name`) }}</span>
                                            <span class="choice-short">{{ $t(`categories.${category.slug}.short`) }}</span>
                                        </span>
                                        <span class="choice-go" aria-hidden="true">{{ $t('landing.card.go') }}</span>
                                    </Link>
                                </li>
                            </ul>
                        </nav>
                    </div>

                    <div class="hero-more">
                        <p class="hero-lead">{{ $t('landing.hero.lead') }}</p>
                        <a href="#numune-sual" class="text-link">{{ $t('landing.hero.sample_link') }}</a>
                    </div>
                </div>
            </section>

            <!-- KATEQORİYALAR -->
            <section id="imtahanlar" class="exams" aria-labelledby="exams-title">
                <div class="wrap">
                    <div class="section-intro">
                        <h2 id="exams-title" class="section-title">{{ $t('landing.exams.title') }}</h2>
                        <p class="section-lead">{{ $t('landing.exams.lead') }}</p>
                    </div>

                    <div class="exam-groups">
                        <section class="group group--education" aria-labelledby="group-education">
                            <h3 id="group-education" class="group-title">{{ $t('landing.exams.education.title') }}</h3>
                            <p class="group-note">{{ $t('landing.exams.education.note') }}</p>

                            <ol class="path">
                                <li class="path-step">
                                    <Link :href="path('mekteb')" class="path-card">
                                        <span class="path-node" aria-hidden="true"></span>
                                        <h4 class="path-name">{{ $t('categories.mekteb.name') }}</h4>
                                        <p class="path-text">{{ $t('landing.exams.education.school') }}</p>
                                        <span class="card-action">{{ $t('landing.exams.action') }}</span>
                                    </Link>
                                </li>
                                <li class="path-step path-step--main">
                                    <Link :href="path('abituriyent')" class="path-card">
                                        <span class="path-node" aria-hidden="true"></span>
                                        <h4 class="path-name">{{ $t('categories.abituriyent.name') }}</h4>
                                        <p class="path-text">{{ $t('landing.exams.education.applicant') }}</p>
                                        <span class="groups-row">
                                            <span class="groups-label">{{ $t('landing.exams.education.groups_label') }}</span>
                                            <span class="groups-cells">
                                                <span>I</span><span>II</span><span>III</span><span>IV</span><span>V</span>
                                            </span>
                                        </span>
                                        <span class="card-action">{{ $t('landing.exams.action') }}</span>
                                    </Link>
                                </li>
                                <li class="path-step">
                                    <Link :href="path('magistratura')" class="path-card">
                                        <span class="path-node" aria-hidden="true"></span>
                                        <h4 class="path-name">{{ $t('categories.magistratura.name') }}</h4>
                                        <p class="path-text">{{ $t('landing.exams.education.master') }}</p>
                                        <span class="card-action">{{ $t('landing.exams.action') }}</span>
                                    </Link>
                                </li>
                            </ol>
                        </section>

                        <section class="group group--career" aria-labelledby="group-career">
                            <div class="form-head">
                                <h3 id="group-career" class="group-title">{{ $t('landing.exams.career.title') }}</h3>
                                <p class="group-note">{{ $t('landing.exams.career.note') }}</p>
                            </div>
                            <ul class="form-rows">
                                <li>
                                    <Link :href="path('dovlet-qullugu')" class="form-row">
                                        <h4 class="form-name">{{ $t('landing.exams.career.civil_name') }}</h4>
                                        <p class="form-text">{{ $t('landing.exams.career.civil_text') }}</p>
                                        <span class="tags">
                                            <span v-for="i in 3" :key="i">{{ $t(`landing.exams.career.civil_tags.${i - 1}`) }}</span>
                                        </span>
                                        <span class="card-action">{{ $t('landing.exams.action') }}</span>
                                    </Link>
                                </li>
                                <li>
                                    <Link :href="path('miq')" class="form-row">
                                        <h4 class="form-name">{{ $t('landing.exams.career.miq_name') }}</h4>
                                        <p class="form-text">{{ $t('landing.exams.career.miq_text') }}</p>
                                        <span class="tags">
                                            <span v-for="i in 3" :key="i">{{ $t(`landing.exams.career.miq_tags.${i - 1}`) }}</span>
                                        </span>
                                        <span class="card-action">{{ $t('landing.exams.action') }}</span>
                                    </Link>
                                </li>
                            </ul>
                        </section>

                        <section class="group group--driving" aria-labelledby="group-driving">
                            <h3 id="group-driving" class="group-title">{{ $t('landing.exams.driving.title') }}</h3>
                            <Link :href="path('suruculuk-imtahani')" class="drive-card">
                                <svg
                                    class="drive-sign"
                                    viewBox="0 0 100 88"
                                    role="img"
                                    aria-labelledby="drive-sign-title"
                                >
                                    <title id="drive-sign-title">{{ $t('landing.exams.driving.sign_alt') }}</title>
                                    <path d="M50 5 L95 83 H5 Z" fill="var(--sign-yellow)" stroke="var(--ink-red)" stroke-width="9" stroke-linejoin="round" />
                                    <rect x="46" y="32" width="8" height="28" rx="2" fill="var(--graphite)" />
                                    <circle cx="50" cy="69" r="4.5" fill="var(--graphite)" />
                                </svg>
                                <div class="drive-body">
                                    <h4 class="drive-name">{{ $t('landing.exams.driving.name') }}</h4>
                                    <p class="drive-text">{{ $t('landing.exams.driving.text') }}</p>
                                    <span class="tags">
                                        <span v-for="i in 3" :key="i">{{ $t(`landing.exams.driving.tags.${i - 1}`) }}</span>
                                    </span>
                                    <span class="card-action">{{ $t('landing.exams.action') }}</span>
                                </div>
                            </Link>
                        </section>
                    </div>
                </div>
            </section>

            <!-- NECƏ İŞLƏYİR -->
            <section class="how" aria-labelledby="how-title">
                <div class="wrap how-grid">
                    <div>
                        <h2 id="how-title" class="section-title">{{ $t('landing.how.title') }}</h2>
                        <ol class="steps">
                            <li v-for="n in 4" :key="n" class="step">
                                <span class="step-num" aria-hidden="true">{{ n }}</span>
                                <h3 class="step-title">{{ $t(`landing.how.steps.${n - 1}.title`) }}</h3>
                                <p class="step-text">{{ $t(`landing.how.steps.${n - 1}.text`) }}</p>
                            </li>
                        </ol>
                    </div>

                    <figure class="result">
                        <figcaption class="result-caption">{{ $t('landing.how.result.caption') }}</figcaption>
                        <div class="result-panel">
                            <p class="result-title">{{ $t('landing.how.result.title') }}</p>
                            <dl class="result-summary">
                                <div><dt>{{ $t('landing.how.result.correct') }}</dt><dd>18</dd></div>
                                <div><dt>{{ $t('landing.how.result.wrong') }}</dt><dd>5</dd></div>
                                <div><dt>{{ $t('landing.how.result.empty') }}</dt><dd>2</dd></div>
                                <div><dt>{{ $t('landing.how.result.time') }}</dt><dd>38:12</dd></div>
                            </dl>
                            <p class="result-subtitle">{{ $t('landing.how.result.topics_title') }}</p>
                            <ul class="topics">
                                <li v-for="topic in topics" :key="topic.key" class="topic" :class="{ 'topic--weak': topic.weak }">
                                    <span class="topic-name">
                                        {{ $t(`landing.how.result.topics.${topic.key}`) }}
                                        <span v-if="topic.weak" class="topic-flag">{{ $t('landing.how.result.weak') }}</span>
                                    </span>
                                    <span class="topic-value">{{ topic.correct }} / {{ topic.total }}</span>
                                    <span class="topic-track" aria-hidden="true">
                                        <span class="topic-bar" :style="{ width: topic.percent + '%' }"></span>
                                    </span>
                                </li>
                            </ul>
                        </div>
                    </figure>
                </div>
            </section>

            <!-- NÜMUNƏ SUAL -->
            <section id="numune-sual" class="sample" aria-labelledby="sample-title">
                <div class="wrap">
                    <div class="section-intro">
                        <h2 id="sample-title" class="section-title">{{ $t('landing.sample.title') }}</h2>
                        <p class="section-lead">{{ $t('landing.sample.lead') }}</p>
                    </div>

                    <div class="sample-grid">
                        <figure class="screen">
                            <div class="screen-bar">
                                <span class="screen-subject">{{ $t('landing.sample.math.subject') }}</span>
                                <span class="screen-count">7 / 25</span>
                                <span class="screen-timer">
                                    <svg viewBox="0 0 16 16" aria-hidden="true"><circle cx="8" cy="9" r="5.5" fill="none" stroke="currentColor" stroke-width="1.5" /><path d="M8 6.5V9l1.8 1.2M6.5 2h3" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" /></svg>
                                    14:32
                                </span>
                            </div>
                            <div class="screen-progress" aria-hidden="true">
                                <span v-for="n in 25" :key="n" :class="{ done: n < 7, current: n === 7 }"></span>
                            </div>
                            <div class="screen-body">
                                <p class="screen-q">{{ $t('landing.sample.math.question') }}</p>
                                <ul class="options">
                                    <li v-for="option in mathOptions" :key="option.letter" class="option" :class="{ 'option--chosen': option.chosen }">
                                        <span class="bubble bubble--sm" aria-hidden="true">{{ option.letter }}</span>
                                        <span>{{ option.text }}</span>
                                        <span v-if="option.chosen" class="visually-hidden">{{ $t('landing.sample.chosen') }}</span>
                                    </li>
                                </ul>
                                <p class="screen-saved">{{ $t('landing.sample.math.saved') }}</p>
                            </div>
                            <div class="screen-nav" aria-hidden="true">
                                <span class="fake-btn">{{ $t('landing.sample.prev') }}</span>
                                <span class="fake-btn fake-btn--primary">{{ $t('landing.sample.next') }}</span>
                            </div>
                            <figcaption class="screen-caption">{{ $t('landing.sample.math.caption') }}</figcaption>
                        </figure>

                        <figure class="screen">
                            <div class="screen-bar">
                                <span class="screen-subject">{{ $t('landing.sample.drive.subject') }}</span>
                                <span class="screen-count">12 / 20</span>
                                <span class="screen-timer">
                                    <svg viewBox="0 0 16 16" aria-hidden="true"><circle cx="8" cy="9" r="5.5" fill="none" stroke="currentColor" stroke-width="1.5" /><path d="M8 6.5V9l1.8 1.2M6.5 2h3" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" /></svg>
                                    08:15
                                </span>
                            </div>
                            <div class="screen-progress" aria-hidden="true">
                                <span v-for="n in 20" :key="n" :class="{ done: n < 12, current: n === 12 }"></span>
                            </div>
                            <div class="screen-body">
                                <svg class="scene" viewBox="0 0 320 180" role="img" aria-labelledby="scene-title">
                                    <title id="scene-title">{{ $t('landing.sample.drive.scene_alt') }}</title>
                                    <rect width="320" height="180" fill="#E6EAE3" />
                                    <rect x="0" y="60" width="320" height="60" fill="#C9CDD2" />
                                    <rect x="130" y="0" width="60" height="180" fill="#C9CDD2" />
                                    <path d="M0 90H122M198 90H320M160 0V52M160 128V180" stroke="#FCFCFA" stroke-width="2" stroke-dasharray="10 8" />
                                    <!-- Göy avtomobil: cənubdan şimala -->
                                    <g>
                                        <rect x="166" y="136" width="18" height="30" rx="4" fill="var(--pen)" />
                                        <rect x="168.5" y="140" width="13" height="7" rx="1.5" fill="#DCE3F5" />
                                        <path d="M175 130V110M169 116l6-7 6 7" fill="none" stroke="var(--pen)" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" />
                                    </g>
                                    <!-- Qırmızı avtomobil: şərqdən qərbə -->
                                    <g>
                                        <rect x="214" y="66" width="30" height="18" rx="4" fill="var(--ink-red)" />
                                        <rect x="218" y="68.5" width="7" height="13" rx="1.5" fill="#F6DCE1" />
                                        <path d="M208 75H188M194 69l-7 6 7 6" fill="none" stroke="var(--ink-red)" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" />
                                    </g>
                                </svg>
                                <p class="screen-q">{{ $t('landing.sample.drive.question') }}</p>
                                <ul class="options">
                                    <li v-for="(number, i) in driveOptions" :key="number" class="option">
                                        <span class="bubble bubble--sm" aria-hidden="true">{{ number }}</span>
                                        <span>{{ $t(`landing.sample.drive.options.${i}`) }}</span>
                                    </li>
                                </ul>
                            </div>
                            <div class="screen-nav" aria-hidden="true">
                                <span class="fake-btn">{{ $t('landing.sample.prev') }}</span>
                                <span class="fake-btn fake-btn--primary">{{ $t('landing.sample.next') }}</span>
                            </div>
                            <figcaption class="screen-caption">{{ $t('landing.sample.drive.caption') }}</figcaption>
                        </figure>
                    </div>
                </div>
            </section>

            <!-- REPETİTORLAR: müəllim modulu aktiv olanda (FEATURE_TEACHERS) -->
            <TutorsSection v-if="teachersEnabled" />
        </main>

        <SiteFooter />
    </div>
</template>


<style scoped>
/*
 * Mobile-first: əsas stillər 360px telefon üçündür,
 * böyük ekranlar yalnız min-width media query-ləri ilə genişlənir (faylın sonunda).
 * Əsas mətn ≥ 16px; yalnız etiket/meta mətnlər (teqlər, sayğac, izahlar) 14px.
 */

/* ---------- Ortaq ---------- */
.section-intro {
    max-width: 640px;
    margin-bottom: 32px;
}

.section-title {
    margin: 0;
    font-family: var(--font-display);
    font-weight: 600;
    font-size: clamp(1.625rem, 1.2rem + 1.9vw, 2.5rem);
    line-height: 1.15;
    letter-spacing: -0.015em;
    text-wrap: balance;
}

.section-lead {
    margin: 12px 0 0;
    max-width: 60ch;
    color: var(--muted);
    font-size: 1rem;
}

.bubble {
    display: inline-grid;
    place-items: center;
    flex: none;
    width: 32px;
    height: 32px;
    border-radius: 50%;
    border: 1.5px solid var(--ink-red);
    color: var(--ink-red);
    font-size: 0.875rem;
    font-weight: 600;
    line-height: 1;
    font-variant-numeric: tabular-nums;
    transition: background-color 150ms ease, color 150ms ease, border-color 150ms ease;
}

.bubble--sm {
    width: 28px;
    height: 28px;
}

.card-action {
    display: inline-block;
    margin-top: 12px;
    font-size: 1rem;
    font-weight: 600;
    color: var(--pen);
    text-decoration: underline;
    text-decoration-thickness: 1.5px;
    text-underline-offset: 4px;
}

.tags {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    margin-top: 12px;
}

.tags span {
    padding: 3px 8px;
    border: 1px solid var(--ink-red-line);
    border-radius: 3px;
    font-size: 0.875rem;
    color: var(--graphite);
    background: var(--paper);
}

/* ---------- Hero ---------- */
.hero {
    padding-block: 20px 48px;
}

/* Telefonda ardıcıllıq: başlıq → cavab kartı → izah */
.hero-grid {
    display: grid;
    grid-template-columns: minmax(0, 1fr);
    gap: 16px;
}

.hero-title {
    margin: 0;
    max-width: 20ch;
    font-family: var(--font-display);
    font-weight: 600;
    font-size: clamp(1.75rem, 1.1rem + 2.9vw, 3.875rem);
    line-height: 1.16;
    letter-spacing: -0.02em;
    /* Başlıq cavab kartının xətləri üzərində yazılıb */
    background-image: linear-gradient(to bottom, transparent calc(100% - 1px), var(--ink-red-line) calc(100% - 1px));
    background-size: 100% 1.16em;
    padding-bottom: 2px;
}

.hero-more {
    margin-top: 12px;
}

.hero-lead {
    margin: 0;
    max-width: 52ch;
    color: var(--muted);
    font-size: 1.0625rem;
}

.text-link {
    display: inline-flex;
    align-items: center;
    min-height: 44px;
    margin-top: 8px;
    color: var(--pen);
    font-size: 1rem;
    font-weight: 600;
    text-decoration: underline;
    text-decoration-thickness: 1.5px;
    text-underline-offset: 5px;
}

.text-link:hover {
    text-decoration-thickness: 2.5px;
}

/* Cavab kartı */
.answer-card {
    position: relative;
    min-width: 0;
    background: #fff;
    border: 1.5px solid var(--ink-red);
    border-radius: 6px;
    padding: 0 12px 2px 24px;
}

.card-head {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 8px 12px;
    margin-inline: -24px -12px;
    padding: 8px 12px 8px 24px;
    border-bottom: 1.5px solid var(--ink-red);
}

.card-label {
    margin: 0;
    color: var(--ink-red);
    font-size: 0.875rem;
    font-weight: 600;
}

.code {
    display: flex;
    align-items: center;
    gap: 8px;
}

.code-label {
    color: var(--ink-red);
    font-size: 0.75rem;
    font-weight: 500;
}

.code-grid {
    display: flex;
    gap: 3px;
}

.code-col {
    display: grid;
    gap: 2px;
}

.code-cell {
    position: relative;
    display: block;
    width: 7px;
    height: 7px;
    border: 1px solid var(--ink-red-line);
    border-radius: 50%;
}

/* Səhifə açılanda yeganə animasiya: xanalar karandaşla doldurulur */
.code-cell--mark::after {
    content: '';
    position: absolute;
    inset: -1px;
    border-radius: 50%;
    background: repeating-linear-gradient(115deg, #262a30 0 1px, #3d434b 1px 2px);
    clip-path: circle(0% at 30% 40%);
    animation: pencil 380ms ease-out forwards;
    animation-delay: calc(500ms + var(--i) * 170ms);
}

@keyframes pencil {
    to { clip-path: circle(75% at 50% 50%); }
}

.card-question {
    margin: 12px 0 0;
    font-size: clamp(1.0625rem, 1rem + 0.4vw, 1.1875rem);
    font-weight: 600;
    line-height: 1.3;
}

.card-hint {
    margin: 2px 0 4px;
    color: var(--muted);
    font-size: 1rem;
}

.choices {
    margin: 0;
    padding: 0;
    list-style: none;
}

.choices li + li {
    border-top: 1px dashed var(--ink-red-line);
}

.choice {
    position: relative;
    display: flex;
    align-items: center;
    gap: 12px;
    min-height: 52px;
    padding-block: 6px;
    color: var(--graphite);
    text-decoration: none;
}

/* Optik oxuyucu üçün kənar nişanları */
.choice::before {
    content: '';
    position: absolute;
    left: -18px;
    top: 50%;
    width: 8px;
    height: 4px;
    margin-top: -2px;
    background: var(--graphite);
}

.choice-text {
    display: grid;
    flex: 1;
    min-width: 0;
}

.choice-name {
    font-weight: 600;
    font-size: 1.0625rem;
    line-height: 1.3;
}

.choice-short {
    color: var(--muted);
    font-size: 0.875rem;
    line-height: 1.35;
}

/* "Başla" yalnız hover olan böyük ekranlarda görünür */
.choice-go {
    display: none;
    flex: none;
    color: var(--pen);
    font-size: 0.875rem;
    font-weight: 600;
}

.choice:hover .bubble,
.choice:focus-visible .bubble {
    background: var(--graphite);
    border-color: var(--graphite);
    color: var(--paper);
}

.choice:active .bubble {
    background: var(--pen);
    border-color: var(--pen);
}

/* ---------- Kateqoriyalar ---------- */
.exams {
    padding-block: 56px;
    border-top: 1px solid var(--ink-red-line);
}

.exam-groups {
    display: grid;
    grid-template-columns: minmax(0, 1fr);
    gap: 44px;
}

.group-title {
    margin: 0;
    font-family: var(--font-display);
    font-weight: 600;
    font-size: clamp(1.25rem, 1.1rem + 0.6vw, 1.375rem);
    line-height: 1.25;
}

.group-note {
    margin: 4px 0 0;
    color: var(--muted);
    font-size: 1rem;
}

/* Təhsil yolu: telefonda şaquli xətt, Abituriyent çərçivəli və böyük */
.path {
    position: relative;
    margin: 20px 0 0;
    padding: 0;
    list-style: none;
    display: grid;
    grid-template-columns: minmax(0, 1fr);
    gap: 8px;
}

.path::before {
    content: '';
    position: absolute;
    left: 9px;
    top: 24px;
    bottom: 24px;
    width: 1.5px;
    background: var(--ink-red);
}

.path-card {
    position: relative;
    display: block;
    height: 100%;
    padding: 14px 12px 16px 36px;
    color: var(--graphite);
    text-decoration: none;
    border-radius: 6px;
}

.path-node {
    position: absolute;
    left: 1px;
    top: 19px;
    width: 18px;
    height: 18px;
    border-radius: 50%;
    border: 1.5px solid var(--ink-red);
    background: var(--paper);
    transition: background-color 150ms ease, border-color 150ms ease;
}

.path-name {
    margin: 0;
    font-family: var(--font-display);
    font-weight: 600;
    font-size: clamp(1.1875rem, 1.05rem + 0.6vw, 1.3125rem);
    line-height: 1.25;
}

.path-text {
    margin: 6px 0 0;
    max-width: 44ch;
    color: var(--muted);
    font-size: 1rem;
}

.path-card:hover .path-node,
.path-card:focus-visible .path-node {
    background: var(--graphite);
    border-color: var(--graphite);
}

.path-card:hover .card-action {
    text-decoration-thickness: 2.5px;
}

.path-step--main .path-card {
    background: #fff;
    border: 1.5px solid var(--ink-red);
    padding-block: 18px 20px;
}

.path-step--main .path-node {
    left: -0.5px;
    top: 21px;
    width: 21px;
    height: 21px;
    background: var(--ink-red);
    box-shadow: inset 0 0 0 3px var(--paper);
}

.path-step--main .path-name {
    font-size: clamp(1.5rem, 1.2rem + 1.3vw, 1.75rem);
}

.path-step--main .path-text {
    color: var(--graphite);
}

.groups-row {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 8px 12px;
    margin-top: 14px;
}

.groups-label {
    color: var(--muted);
    font-size: 0.875rem;
}

.groups-cells {
    display: flex;
    flex-wrap: wrap;
    padding-left: 1px;
}

.groups-cells span {
    display: grid;
    place-items: center;
    min-width: 2.125rem;
    height: 1.875rem;
    margin-bottom: -1px;
    padding-inline: 4px;
    border: 1px solid var(--ink-red-line);
    margin-left: -1px;
    font-family: var(--font-display);
    font-size: 0.875rem;
    font-weight: 600;
    color: var(--ink-red);
}

/* Peşə imtahanları: rəsmi forma */
.group--career {
    border: 1.5px solid var(--graphite);
    border-radius: 4px;
    background: #fff;
}

.form-head {
    padding: 14px 16px;
    border-bottom: 1.5px solid var(--graphite);
}

.form-rows {
    margin: 0;
    padding: 0;
    list-style: none;
}

.form-rows li + li {
    border-top: 1px solid var(--ink-red-line);
}

.form-row {
    display: block;
    padding: 16px;
    color: var(--graphite);
    text-decoration: none;
}

.form-name {
    margin: 0;
    font-size: clamp(1.125rem, 1.05rem + 0.35vw, 1.1875rem);
    font-weight: 600;
    line-height: 1.3;
}

.form-text {
    margin: 4px 0 0;
    color: var(--muted);
    font-size: 1rem;
}

.form-row:hover {
    background: var(--paper-sunk);
}

/* Sürücülük */
.group--driving .group-title {
    margin-bottom: 14px;
}

.drive-card {
    display: flex;
    gap: 14px;
    align-items: flex-start;
    padding: 18px 16px;
    border-top: 3px solid var(--ink-red);
    background: var(--paper-sunk);
    color: var(--graphite);
    text-decoration: none;
}

.drive-sign {
    flex: none;
    width: 48px;
    height: auto;
}

.drive-body {
    min-width: 0;
}

.drive-name {
    margin: 0;
    font-size: clamp(1.125rem, 1.05rem + 0.35vw, 1.1875rem);
    font-weight: 600;
    line-height: 1.3;
}

.drive-text {
    margin: 4px 0 0;
    color: var(--muted);
    font-size: 1rem;
}

.drive-card:hover .card-action,
.form-row:hover .card-action {
    text-decoration-thickness: 2.5px;
}

/* ---------- Necə işləyir ---------- */
.how {
    padding-block: 56px;
    background: var(--paper-sunk);
    border-top: 1px solid var(--ink-red-line);
    border-bottom: 1px solid var(--ink-red-line);
}

.how-grid {
    display: grid;
    grid-template-columns: minmax(0, 1fr);
    gap: 40px;
}

.steps {
    margin: 28px 0 0;
    padding: 0;
    list-style: none;
    display: grid;
    gap: 22px;
}

.step {
    display: grid;
    grid-template-columns: 32px minmax(0, 1fr);
    column-gap: 14px;
}

.step-num {
    grid-row: span 2;
    display: grid;
    place-items: center;
    width: 32px;
    height: 32px;
    border-radius: 50%;
    border: 1.5px solid var(--graphite);
    font-weight: 600;
    font-size: 0.9375rem;
    font-variant-numeric: tabular-nums;
}

.step-title {
    margin: 3px 0 0;
    font-size: clamp(1.0625rem, 1rem + 0.3vw, 1.125rem);
    font-weight: 600;
    line-height: 1.3;
}

.step-text {
    margin: 4px 0 0;
    max-width: 48ch;
    color: var(--muted);
    font-size: 1rem;
}

.result {
    margin: 0;
    align-self: start;
    min-width: 0;
}

.result-caption {
    margin-bottom: 8px;
    color: var(--muted);
    font-size: 0.875rem;
}

.result-panel {
    padding: 18px 16px;
    background: #fff;
    border: 1px solid var(--ink-red-line);
    border-radius: 6px;
}

.result-title {
    margin: 0;
    font-weight: 600;
    font-size: 1.0625rem;
}

/* Telefonda 2×2, genişdə 4 sütun */
.result-summary {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 1px;
    margin: 14px 0 0;
    border: 1px solid var(--ink-red-line);
    border-radius: 4px;
    background: var(--ink-red-line);
    overflow: hidden;
}

.result-summary div {
    padding: 8px 12px;
    background: #fff;
}

.result-summary dt {
    color: var(--muted);
    font-size: 0.875rem;
}

.result-summary dd {
    margin: 0;
    font-size: 1.25rem;
    font-weight: 600;
    font-variant-numeric: tabular-nums;
}

.result-subtitle {
    margin: 22px 0 12px;
    font-size: 1rem;
    font-weight: 600;
}

.topics {
    margin: 0;
    padding: 0;
    list-style: none;
    display: grid;
    gap: 14px;
}

.topic {
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    align-items: baseline;
    column-gap: 12px;
    row-gap: 6px;
    font-size: 1rem;
}

.topic-flag {
    display: inline-block;
    margin-left: 6px;
    padding: 0 6px;
    border: 1px solid var(--ink-red);
    border-radius: 3px;
    color: var(--ink-red);
    font-size: 0.875rem;
    font-weight: 600;
    white-space: nowrap;
}

.topic-value {
    font-variant-numeric: tabular-nums;
    font-weight: 600;
}

.topic-track {
    grid-column: 1 / -1;
    display: block;
    height: 8px;
    border-radius: 4px;
    background: var(--paper-sunk);
}

.topic-bar {
    display: block;
    height: 100%;
    border-radius: 4px;
    background: var(--graphite);
}

.topic--weak .topic-bar {
    background: var(--ink-red);
}

/* ---------- Nümunə sual ---------- */
.sample {
    padding-block: 56px;
    scroll-margin-top: 8px;
}

/* Telefonda iki maket bir-birinin altında, tam enində */
.sample-grid {
    display: grid;
    grid-template-columns: minmax(0, 1fr);
    gap: 28px;
}

.screen {
    margin: 0;
    min-width: 0;
    display: flex;
    flex-direction: column;
    background: #fff;
    border: 1.5px solid var(--graphite);
    border-radius: 10px;
    overflow: hidden;
}

.screen-bar {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 8px 12px;
    padding: 10px 14px;
    border-bottom: 1px solid var(--ink-red-line);
    font-size: 0.875rem;
}

/* Yer çatmayanda say və sayğac alt sətrə keçir, fənn adı hərf-hərf bölünmür */
.screen-subject {
    flex: 1 1 8rem;
    min-width: 0;
    font-weight: 600;
}

.screen-count {
    color: var(--muted);
    font-variant-numeric: tabular-nums;
}

.screen-timer {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 4px 8px;
    border-radius: 4px;
    background: var(--graphite);
    color: var(--paper);
    font-weight: 600;
    font-variant-numeric: tabular-nums;
}

.screen-timer svg {
    width: 14px;
    height: 14px;
}

.screen-progress {
    display: flex;
    gap: 2px;
    padding: 10px 14px 0;
}

.screen-progress span {
    flex: 1;
    height: 6px;
    border-radius: 2px;
    background: var(--paper-sunk);
}

.screen-progress .done {
    background: var(--graphite);
}

.screen-progress .current {
    background: var(--pen);
}

.screen-body {
    flex: 1;
    padding: 18px 14px;
}

.screen-q {
    margin: 0 0 14px;
    font-size: 1.0625rem;
    font-weight: 500;
    line-height: 1.5;
}

.scene {
    display: block;
    width: 100%;
    height: auto;
    margin-bottom: 14px;
    border-radius: 6px;
}

.options {
    margin: 0;
    padding: 0;
    list-style: none;
    display: grid;
    gap: 8px;
}

.option {
    display: flex;
    align-items: center;
    gap: 12px;
    min-height: 48px;
    padding: 6px 12px;
    border: 1px solid var(--ink-red-line);
    border-radius: 6px;
    font-size: 1rem;
    font-variant-numeric: tabular-nums;
}

.option--chosen {
    border-color: var(--graphite);
    box-shadow: inset 0 0 0 1px var(--graphite);
}

.option--chosen .bubble {
    background: var(--graphite);
    border-color: var(--graphite);
    color: var(--paper);
}

.screen-saved {
    margin: 12px 0 0;
    color: var(--correct);
    font-size: 0.875rem;
    font-weight: 500;
}

.screen-nav {
    display: flex;
    flex-wrap: wrap;
    justify-content: space-between;
    gap: 12px;
    padding: 12px 14px;
    border-top: 1px solid var(--ink-red-line);
}

.fake-btn {
    display: inline-flex;
    align-items: center;
    min-height: 44px;
    padding-inline: 16px;
    border: 1.5px solid var(--graphite);
    border-radius: 6px;
    font-size: 1rem;
    font-weight: 600;
}

.fake-btn--primary {
    background: var(--pen);
    border-color: var(--pen);
    color: #fff;
}

.screen-caption {
    padding: 12px 14px;
    background: var(--paper-sunk);
    color: var(--muted);
    font-size: 0.875rem;
}

@media (prefers-reduced-motion: reduce) {
    .code-cell--mark::after {
        animation: none;
        clip-path: none;
    }

    .bubble,
    .path-node {
        transition: none;
    }
}

/* ================= Böyük ekranlar (yalnız min-width) ================= */
@media (min-width: 480px) {
    .result-summary {
        grid-template-columns: repeat(4, minmax(0, 1fr));
    }
}

@media (min-width: 720px) {
    .answer-card {
        padding: 0 16px 6px 30px;
    }

    .card-head {
        margin-inline: -30px -16px;
        padding: 12px 16px 12px 30px;
    }

    .code-grid { gap: 4px; }
    .code-col { gap: 3px; }

    .code-cell {
        width: 9px;
        height: 9px;
    }

    .choice {
        min-height: 60px;
        gap: 14px;
    }

    .choice::before {
        left: -21px;
        width: 9px;
    }

    .bubble {
        width: 34px;
        height: 34px;
    }

    .bubble--sm {
        width: 28px;
        height: 28px;
    }

    .path {
        grid-template-columns: minmax(0, 1fr) minmax(0, 1.45fr) minmax(0, 1fr);
        gap: 16px;
    }

    .path::before {
        left: 24px;
        right: 24px;
        top: 30px;
        bottom: auto;
        width: auto;
        height: 1.5px;
    }

    .path-card {
        padding: 56px 16px 20px;
    }

    .path-node {
        left: 16px;
        top: 21px;
    }

    .path-step--main .path-card {
        padding: 56px 20px 24px;
        margin-top: -1px;
    }

    .path-step--main .path-node {
        left: 20px;
        top: 19.5px;
    }

    .drive-sign {
        width: 64px;
    }

    .sample-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
        align-items: stretch;
    }

    .result-panel {
        padding: 24px;
    }

}

@media (min-width: 720px) and (hover: hover) {
    .choice-go {
        display: inline;
        opacity: 0;
        transition: opacity 150ms ease;
    }

    .choice:hover .choice-go,
    .choice:focus-visible .choice-go {
        opacity: 1;
    }
}

@media (min-width: 960px) {
    .hero {
        padding-block: 72px 96px;
    }

    /* Solda başlıq və izah, sağda kart; sol tərəf şaquli mərkəzdə */
    .hero-grid {
        grid-template-columns: minmax(0, 1.1fr) minmax(0, 1fr);
        grid-template-rows: 1fr auto auto 1fr;
        grid-template-areas:
            '. card'
            'title card'
            'more card'
            '. card';
        column-gap: 64px;
        row-gap: 0;
    }

    .hero-title {
        grid-area: title;
        max-width: 15ch;
    }

    .answer-card {
        grid-area: card;
    }

    .hero-more {
        grid-area: more;
        margin-top: 24px;
    }

    .exams,
    .how,
    .sample {
        padding-block: 96px;
    }

    .section-intro {
        margin-bottom: 40px;
    }

    .exam-groups {
        grid-template-columns: minmax(0, 7fr) minmax(0, 5fr);
        column-gap: 32px;
        row-gap: 56px;
    }

    .group--education {
        grid-column: 1 / -1;
    }

    .group--driving {
        align-self: start;
    }

    .how-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 64px;
        align-items: start;
    }

}

@media (min-width: 720px) and (prefers-reduced-motion: reduce) {
    .choice-go {
        transition: none;
    }
}
</style>

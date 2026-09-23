<script setup>
import { computed, ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';

/**
 * Kataloq kartı — kateqoriya səhifəsində və ümumi kataloqda eyni komponent.
 * Məlumat forması `CategoryController::examCard()`-dan gəlir.
 *
 * KLİK SAHƏSİ: kart adi blokdur, link isə BAŞLIQDADIR və `::after` ilə bütün kartı örtür
 * ("stretched link"). Beləcə fokus başlığa düşür, ekran oxuyucusunda link mətni qısa qalır,
 * amma kartın istənilən yerinə klik işləyir. Linkin `aria-label`-ı bölmə yolunu da əlavə
 * edir ki, siyahıdakı linklər bir-birindən seçilsin.
 *
 * RƏNG: bölmənin rəngi `--cat` dəyişəninə yazılır — qlobal stil faylına toxunmadan üst
 * zolaq və nişan həmin rəngi alır. Rəng TƏK məlumat daşıyıcısı deyil: bölmənin adı və
 * növün adı mətnlə də yazılır.
 */
const props = defineProps({
    exam: { type: Object, required: true },
    // Kateqoriya səhifəsində yol artıq başlıqdadır; kataloqda göstərilir
    showTrail: { type: Boolean, default: true },
});

const trail = computed(() => props.exam.trail ?? {});

const color = computed(() => trail.value.color || 'var(--muted)');

const trailText = computed(
    () => [trail.value.root, trail.value.leaf].filter(Boolean).join(' › '),
);

/*
 * Növ etiketi: "Mövzu sınağı — 2-ci rüb". Başlıq imtahanın ÖZ ADIDIR, bu isə onun altında
 * kiçik etiket kimi durur.
 */
const kindLabel = computed(() => {
    const kind = trans(`category_page.kinds.${props.exam.kind}`);

    return props.exam.quarter
        ? `${kind} — ${trans('category_page.quarter', { number: props.exam.quarter })}`
        : kind;
});

/*
 * Ad avtomatik qurulub və kateqoriya + növdən başqa heç nə demirsə server `title`-ı null
 * göndərir (`ExamTitle::isGeneric()`): belə kartda başlıq elə növ etiketidir, eyni söz
 * iki dəfə yazılmır.
 */
const heading = computed(() => props.exam.title || kindLabel.value);

const showKindTag = computed(() => Boolean(props.exam.title));

// Ekran oxuyucusunda linklər bir-birindən seçilsin deyə yol da adın içindədir
const ariaLabel = computed(
    () => (trailText.value ? `${trailText.value} — ${heading.value}` : heading.value),
);

/** Sinif etiketi kartda nişan kimi görünür (məs. "9-cu sinif") */
const gradeTags = computed(() => (props.exam.tags ?? []).filter((tag) => tag.kind === 'grade'));

const otherTags = computed(() => (props.exam.tags ?? []).filter((tag) => tag.kind !== 'grade'));

/*
 * "Ətraflı": bölmələr, müddət və izah KART DAXİLİNDƏ açılır — səhifəni tərk etmək
 * lazım gəlmir. Düymə `position: relative` ilə başlığın örtən linkindən (stretched
 * link) YUXARIDA durur, ona görə kliki imtahan səhifəsini açmır.
 */
const open = ref(false);

const detailsId = computed(() => `exam-details-${props.exam.id}`);

const hasDetails = computed(
    () => Boolean(props.exam.description) || (props.exam.sections ?? []).length > 0,
);
</script>

<template>
    <article class="card" :style="{ '--cat': color }">
        <span class="card-bar" aria-hidden="true"></span>

        <p v-if="showTrail && trailText" class="card-trail">
            <span class="card-dot" aria-hidden="true"></span>{{ trailText }}
        </p>

        <h3 class="card-title">
            <Link :href="exam.url" class="card-link" :aria-label="ariaLabel">
                {{ heading }}
            </Link>
        </h3>

        <p v-if="showKindTag" class="card-kind">
            <span class="tag tag--kind" :class="`tag--kind-${exam.kind}`">{{ kindLabel }}</span>
        </p>

        <p class="card-meta">
            <template v-if="exam.subjects.length">{{ exam.subjects.join(', ') }} · </template>
            {{ exam.questions_count }} {{ $t('category_page.questions') }}
            · {{ exam.duration_minutes }} {{ $t('category_page.minutes') }}
        </p>

        <p v-if="gradeTags.length" class="card-grades">
            <span v-for="tag in gradeTags" :key="tag.id" class="tag tag--grade">{{ tag.name }}</span>
        </p>

        <p class="card-foot">
            <span v-if="exam.is_free" class="tag tag--free">{{ $t('category_page.free') }}</span>
            <span v-else class="tag tag--price">{{ exam.price }} AZN</span>

            <button
                v-if="hasDetails"
                type="button"
                class="details-toggle"
                :aria-expanded="open"
                :aria-controls="detailsId"
                @click="open = !open"
            >{{ open ? $t('exam_catalog.details_hide') : $t('exam_catalog.details') }}</button>
        </p>

        <div v-if="open" :id="detailsId" class="details">
            <ul v-if="exam.sections?.length" class="details-list">
                <li v-for="(section, index) in exam.sections" :key="index">
                    <span class="details-name">{{ section.subject }}</span>
                    <span class="details-value">{{ section.question_count }} {{ $t('category_page.questions') }}</span>
                </li>
            </ul>

            <p class="details-meta">
                {{ exam.duration_minutes }} {{ $t('category_page.minutes') }}
                · {{ exam.questions_count }} {{ $t('category_page.questions') }}
                <template v-if="otherTags.length">
                    · {{ otherTags.map((tag) => tag.name).join(', ') }}
                </template>
            </p>

            <p v-if="exam.description" class="details-text">{{ exam.description }}</p>
        </div>
    </article>
</template>

<style scoped>
.card {
    position: relative;
    display: flex;
    flex-direction: column;
    gap: 6px;
    /* Grid övladı: uzun mətn sütunu genişləndirib üfüqi sürüşmə yaratmasın */
    min-width: 0;
    height: 100%;
    padding: 18px 16px 16px;
    border: 1px solid rgba(22, 19, 14, 0.15);
    border-radius: 12px;
    background: var(--paper);
    transition: border-color 150ms ease, box-shadow 150ms ease;
}

/* Bölmənin rəngli zolağı */
.card-bar {
    position: absolute;
    inset: 0 0 auto;
    height: 4px;
    border-radius: 12px 12px 0 0;
    background: var(--cat);
}

.card:hover {
    border-color: rgba(22, 19, 14, 0.4);
    box-shadow: 0 6px 18px rgba(22, 19, 14, 0.07);
}

/* Fokus bütün karta görünsün — link görünməz şəkildə kartı örtür */
.card:focus-within {
    border-color: var(--pen);
    box-shadow: 0 0 0 2px var(--pen);
}

.card-trail {
    display: flex;
    align-items: center;
    gap: 6px;
    margin: 0;
    min-width: 0;
    font-size: 0.8125rem;
    font-weight: 600;
    letter-spacing: 0.01em;
    color: var(--cat);
}

.card-dot {
    width: 8px;
    height: 8px;
    flex: none;
    border-radius: 50%;
    background: var(--cat);
}

.card-title {
    margin: 0;
    font-size: 1.0625rem;
    font-weight: 600;
    line-height: 1.3;
}

.card-link {
    color: var(--graphite);
    text-decoration: none;
}

/* Görünməz klik sahəsi: bütün kart klikləniən olur, link mətni qısa qalır */
.card-link::after {
    content: '';
    position: absolute;
    inset: 0;
    border-radius: 12px;
}

.card-link:hover {
    color: var(--pen);
}

/* Fokus halqası kartın özündədir (:focus-within), link ayrıca halqa çəkmir */
.card-link:focus-visible {
    outline: none;
}

/* Növ etiketi: başlığın altında, kiçik və neytral — başlıqla yarışmır */
.card-kind {
    margin: 0;
}

.card-meta {
    margin: 0;
    font-size: 0.9375rem;
    color: var(--muted);
}

.card-foot {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 6px;
    margin: auto 0 0;
    padding-top: 10px;
}

.tag {
    display: inline-flex;
    align-items: center;
    padding: 4px 10px;
    border-radius: 999px;
    font-size: 0.8125rem;
    font-weight: 600;
    line-height: 1.4;
}

/* Növ nişanı: neytral, kənarındakı rəngli zolaqla tanınır (rəng tək göstərici deyil) */
.tag--kind {
    border: 1px solid rgba(22, 19, 14, 0.2);
    color: var(--muted);
}

.tag--kind-general { border-left: 3px solid var(--graphite); }
.tag--kind-topic_trial { border-left: 3px solid var(--pen); }
.tag--kind-subject { border-left: 3px solid #6B3FA0; }
.tag--kind-practice { border-left: 3px solid #0F766E; }

/* Rəng tək göstərici deyil: nişanın mətni də var */
.tag--free {
    background: #E6EFEA;
    color: var(--correct);
}

.tag--price {
    background: var(--paper-sunk);
    color: var(--graphite);
}

.tag--grade {
    background: #E9ECF6;
    color: var(--pen);
}

.card-grades {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    margin: 2px 0 0;
}

/* ------------------------------------------------------------- Ətraflı */

/*
 * Düymə örtən linkdən (`.card-link::after`) YUXARIDADIR: `position: relative` +
 * `z-index` olmasa klik kartın əsas linkini tətikləyərdi.
 */
.details-toggle {
    position: relative;
    z-index: 1;
    margin-left: auto;
    /* Toxunma sahəsi 44px, amma kartı hündürlətməsin deyə mənfi boşluqla */
    min-height: 44px;
    margin-block: -10px;
    padding: 10px 4px;
    border: 0;
    background: none;
    font: inherit;
    font-size: 0.875rem;
    font-weight: 600;
    color: var(--pen);
    text-decoration: underline;
    text-underline-offset: 4px;
    cursor: pointer;
}

.details {
    position: relative;
    z-index: 1;
    margin-top: 10px;
    padding-top: 10px;
    border-top: 1px dashed var(--ink-red-line);
}

.details-list {
    list-style: none;
    margin: 0 0 8px;
    padding: 0;
    display: grid;
    gap: 4px;
}

.details-list li {
    display: flex;
    flex-wrap: wrap;
    justify-content: space-between;
    gap: 8px;
    font-size: 0.875rem;
}

.details-name {
    font-weight: 600;
    color: var(--graphite);
}

.details-value {
    color: var(--muted);
}

.details-meta {
    margin: 0;
    font-size: 0.8125rem;
    color: var(--muted);
}

.details-text {
    margin: 8px 0 0;
    font-size: 0.875rem;
    color: var(--muted);
}

@media (min-width: 640px) {
    .card-meta { font-size: 0.9rem; }
}
</style>

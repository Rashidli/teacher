<script setup>
import { computed } from 'vue';
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

// Başlıq: növ (+ rüb). İmtahanın öz adı kartda təkrarlanmır — o, imtahan səhifəsindədir.
const heading = computed(() => {
    const kind = trans(`category_page.kinds.${props.exam.kind}`);

    return props.exam.quarter
        ? `${kind} — ${trans('category_page.quarter', { number: props.exam.quarter })}`
        : kind;
});

// Ekran oxuyucusunda linklər bir-birindən seçilsin deyə yol da adın içindədir
const ariaLabel = computed(
    () => (trailText.value ? `${trailText.value} — ${heading.value}` : heading.value),
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

        <p class="card-meta">
            <template v-if="exam.subjects.length">{{ exam.subjects.join(', ') }} · </template>
            {{ exam.questions_count }} {{ $t('category_page.questions') }}
            · {{ exam.duration_minutes }} {{ $t('category_page.minutes') }}
        </p>

        <p class="card-foot">
            <span v-if="exam.is_free" class="tag tag--free">{{ $t('category_page.free') }}</span>
            <span v-else class="tag tag--price">{{ exam.price }} AZN</span>
        </p>
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

/* Rəng tək göstərici deyil: nişanın mətni də var */
.tag--free {
    background: #E6EFEA;
    color: var(--correct);
}

.tag--price {
    background: var(--paper-sunk);
    color: var(--graphite);
}

@media (min-width: 640px) {
    .card-meta { font-size: 0.9rem; }
}
</style>

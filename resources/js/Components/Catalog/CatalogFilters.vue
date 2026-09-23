<script setup>
import { computed, ref } from 'vue';

/**
 * Kataloq filtr paneli — kateqoriya səhifəsində və ümumi kataloqda eyni komponent.
 *
 * Seçim URL-də query kimi qalır: komponent özü naviqasiya etmir, `update` hadisəsi ilə
 * açar/dəyər qaytarır, səhifə isə onu `router.get()` ilə ünvana yazır.
 *
 * Mobildə panel bağlıdır (açar düymədə aktiv filtr sayı görünür), ≥768px-dən həmişə açıq.
 */
const props = defineProps({
    // Hansı ölçülər göstərilsin: 'kateqoriya' | 'nov' | 'rub' | 'fenn' | 'qiymet'
    facets: { type: Array, default: () => ['nov', 'rub', 'fenn', 'qiymet'] },
    options: { type: Object, default: () => ({}) },
    filters: { type: Object, default: () => ({}) },
    // 'top' — siyahının üstündə, 'side' — masaüstündə yan sütunda
    layout: { type: String, default: 'top' },
});

const emit = defineEmits(['update', 'reset']);

const open = ref(false);

const has = (facet) => props.facets.includes(facet);

const list = (key) => props.options[key] ?? [];

const active = computed(
    () => Object.values(props.filters).filter((value) => value !== null && value !== '').length,
);

// Rüb yalnız mövzu sınağı seçiləndə mənalıdır — backend də eyni qaydanı tətbiq edir
const showQuarters = computed(
    () => has('rub') && props.filters.nov === 'topic_trial' && list('quarters').length > 0,
);

const visible = computed(() => has('nov') && list('kinds').length > 1
    || has('fenn') && list('subjects').length > 1
    || has('qiymet') && list('prices').length > 1
    || has('kateqoriya') && list('categories').length > 1);

defineExpose({ visible });
</script>

<template>
    <section v-if="visible" class="filters-box" :class="`filters-box--${layout}`">
        <!-- Mobil açar: masaüstündə gizlənir, panel həmişə açıq olur -->
        <button
            type="button"
            class="filters-toggle"
            :aria-expanded="open"
            aria-controls="catalog-filters"
            @click="open = !open"
        >
            <span>{{ open ? $t('exam_catalog.filters_close') : $t('exam_catalog.filters_open') }}</span>
            <span v-if="active" class="filters-badge">{{ active }}</span>
        </button>

        <div id="catalog-filters" class="filters" :class="{ 'filters--open': open }">
            <h2 class="filters-title">{{ $t('exam_catalog.filters') }}</h2>

            <!-- Kateqoriya: kök və ikinci səviyyə düyünlər (yalnız ümumi kataloqda) -->
            <div v-if="has('kateqoriya') && list('categories').length > 1" class="filter">
                <span :id="`f-cat`" class="filter-label">{{ $t('exam_catalog.filter_category') }}</span>
                <div class="chips" role="group" :aria-labelledby="`f-cat`">
                    <button
                        type="button"
                        class="chip"
                        :class="{ 'chip--on': !filters.kateqoriya }"
                        :aria-pressed="!filters.kateqoriya"
                        @click="emit('update', 'kateqoriya', null)"
                    >{{ $t('category_page.filter_all') }}</button>
                    <button
                        v-for="option in list('categories')"
                        :key="option.value"
                        type="button"
                        class="chip"
                        :class="{ 'chip--on': filters.kateqoriya === option.value, 'chip--child': option.depth > 0 }"
                        :aria-pressed="filters.kateqoriya === option.value"
                        @click="emit('update', 'kateqoriya', option.value)"
                    >{{ option.name }} ({{ option.count }})</button>
                </div>
            </div>

            <div v-if="has('nov') && list('kinds').length > 1" class="filter">
                <span id="f-kind" class="filter-label">{{ $t('category_page.filter_kind') }}</span>
                <div class="chips" role="group" aria-labelledby="f-kind">
                    <button
                        type="button"
                        class="chip"
                        :class="{ 'chip--on': !filters.nov }"
                        :aria-pressed="!filters.nov"
                        @click="emit('update', 'nov', null)"
                    >{{ $t('category_page.filter_all') }}</button>
                    <button
                        v-for="option in list('kinds')"
                        :key="option.value"
                        type="button"
                        class="chip"
                        :class="{ 'chip--on': filters.nov === option.value }"
                        :aria-pressed="filters.nov === option.value"
                        @click="emit('update', 'nov', option.value)"
                    >{{ $t(`category_page.kinds.${option.value}`) }} ({{ option.count }})</button>
                </div>
            </div>

            <div v-if="showQuarters" class="filter">
                <span id="f-quarter" class="filter-label">{{ $t('category_page.filter_quarter') }}</span>
                <div class="chips" role="group" aria-labelledby="f-quarter">
                    <button
                        type="button"
                        class="chip"
                        :class="{ 'chip--on': !filters.rub }"
                        :aria-pressed="!filters.rub"
                        @click="emit('update', 'rub', null)"
                    >{{ $t('category_page.filter_all') }}</button>
                    <button
                        v-for="option in list('quarters')"
                        :key="option.value"
                        type="button"
                        class="chip"
                        :class="{ 'chip--on': filters.rub === option.value }"
                        :aria-pressed="filters.rub === option.value"
                        @click="emit('update', 'rub', option.value)"
                    >{{ option.value }}-ci rüb ({{ option.count }})</button>
                </div>
            </div>

            <div v-if="has('fenn') && list('subjects').length > 1" class="filter">
                <span id="f-subject" class="filter-label">{{ $t('category_page.filter_subject') }}</span>
                <div class="chips" role="group" aria-labelledby="f-subject">
                    <button
                        type="button"
                        class="chip"
                        :class="{ 'chip--on': !filters.fenn }"
                        :aria-pressed="!filters.fenn"
                        @click="emit('update', 'fenn', null)"
                    >{{ $t('category_page.filter_all') }}</button>
                    <button
                        v-for="option in list('subjects')"
                        :key="option.value"
                        type="button"
                        class="chip"
                        :class="{ 'chip--on': filters.fenn === option.value }"
                        :aria-pressed="filters.fenn === option.value"
                        @click="emit('update', 'fenn', option.value)"
                    >{{ option.name }} ({{ option.count }})</button>
                </div>
            </div>

            <div v-if="has('qiymet') && list('prices').length > 1" class="filter">
                <span id="f-price" class="filter-label">{{ $t('category_page.filter_price') }}</span>
                <div class="chips" role="group" aria-labelledby="f-price">
                    <button
                        type="button"
                        class="chip"
                        :class="{ 'chip--on': !filters.qiymet }"
                        :aria-pressed="!filters.qiymet"
                        @click="emit('update', 'qiymet', null)"
                    >{{ $t('category_page.filter_all') }}</button>
                    <button
                        v-for="option in list('prices')"
                        :key="option.value"
                        type="button"
                        class="chip"
                        :class="{ 'chip--on': filters.qiymet === option.value }"
                        :aria-pressed="filters.qiymet === option.value"
                        @click="emit('update', 'qiymet', option.value)"
                    >{{ $t(`category_page.prices.${option.value}`) }} ({{ option.count }})</button>
                </div>
            </div>

            <button v-if="active" type="button" class="filters-reset" @click="emit('reset')">
                {{ $t('exam_catalog.reset') }}
            </button>
        </div>
    </section>
</template>

<style scoped>
.filters-box {
    margin-bottom: 20px;
}

/* Mobil açar düyməsi: masaüstündə (≥768px) gizlənir */
.filters-toggle {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    min-height: 44px;
    padding: 8px 16px;
    border: 1px solid rgba(22, 19, 14, 0.25);
    border-radius: 999px;
    background: none;
    font: inherit;
    cursor: pointer;
}

.filters-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 22px;
    height: 22px;
    padding-inline: 6px;
    border-radius: 999px;
    background: var(--pen);
    color: var(--paper);
    font-size: 0.8125rem;
    font-weight: 600;
}

/* Mobildə panel yalnız açar basılanda görünür */
.filters {
    display: none;
    gap: 14px;
    margin-top: 14px;
}

.filters--open {
    display: grid;
}

.filters-title {
    /* Mobildə panelin içində başlıq lazım deyil — açar düymə onu onsuz da bildirir */
    position: absolute;
    width: 1px;
    height: 1px;
    overflow: hidden;
    clip: rect(0 0 0 0);
    white-space: nowrap;
}

.filter {
    display: grid;
    gap: 8px;
}

.filter-label {
    font-size: 0.9375rem;
    opacity: 0.7;
}

.chips {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

/*
 * Toxunma sahəsi 44px (padding ilə), mətn ölçüsü isə dəyişmir —
 * çiplər sıx düzülür, amma barmaqla rahat seçilir.
 */
.chip {
    min-height: 44px;
    /* Uzun kateqoriya adı konteynerdən enli olmasın (360px-də üfüqi sürüşmə) */
    max-width: 100%;
    overflow-wrap: anywhere;
    padding: 10px 14px;
    border: 1px solid rgba(22, 19, 14, 0.25);
    border-radius: 999px;
    background: none;
    font: inherit;
    font-size: 0.95rem;
    line-height: 1.2;
    text-align: left;
    cursor: pointer;
}

.chip--on {
    border-color: rgba(22, 19, 14, 0.7);
    font-weight: 600;
}

/* İkinci səviyyə kateqoriya: kökdən vizual olaraq ayrılır */
.chip--child {
    border-style: dashed;
    opacity: 0.9;
}

.filters-reset {
    justify-self: start;
    min-height: 44px;
    padding: 10px 4px;
    border: 0;
    background: none;
    font: inherit;
    font-size: 0.95rem;
    color: var(--pen);
    text-decoration: underline;
    cursor: pointer;
}

@media (min-width: 768px) {
    .filters-toggle {
        display: none;
    }

    /* Masaüstündə panel həmişə açıqdır */
    .filters {
        display: grid;
        margin-top: 0;
    }

    .filter {
        grid-template-columns: 96px 1fr;
        align-items: start;
    }

    .filter-label {
        padding-top: 12px;
    }
}

/* Yan sütun: etiket çiplərin üstündə qalır, sütun dar olduğu üçün */
@media (min-width: 1024px) {
    .filters-box--side .filter {
        grid-template-columns: 1fr;
    }

    .filters-box--side .filter-label {
        padding-top: 0;
        font-weight: 600;
        opacity: 0.85;
    }

    .filters-box--side .filters-title {
        position: static;
        width: auto;
        height: auto;
        clip: auto;
        margin: 0;
        font-size: 1.05rem;
    }
}
</style>

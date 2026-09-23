<script setup>
import { computed, ref } from 'vue';
import { trans } from 'laravel-vue-i18n';

/**
 * Kataloq filtr paneli — kateqoriya səhifəsində və ümumi kataloqda eyni komponent.
 *
 * Komponent özü naviqasiya etmir: `update` hadisəsi ilə açar/dəyər qaytarır, səhifə isə
 * onu URL query-sinə yazır. Beləcə süzülmüş səhifə paylaşıla bilir.
 *
 * YIĞCAMLIQ: kateqoriya siyahısı ikisəviyyəli akkordeondur (kök sətri, açılanda alt
 * düyünlər), fənn siyahısı isə ilk altıdan sonra "Daha çox" ilə açılır — panel uzun
 * kataloqda da ekranı doldurmur. Mobildə panel bağlıdır, ≥768px-dən həmişə açıq.
 */
const props = defineProps({
    // Hansı ölçülər göstərilsin: 'sektor' | 'kateqoriya' | 'nov' | 'rub' | 'fenn' | 'qiymet'
    facets: { type: Array, default: () => ['nov', 'rub', 'fenn', 'etiket', 'qiymet'] },
    options: { type: Object, default: () => ({}) },
    filters: { type: Object, default: () => ({}) },
    // 'top' — siyahının üstündə, 'side' — masaüstündə yan sütunda
    layout: { type: String, default: 'top' },
    sector: { type: String, default: 'az' },
    canSwitchSector: { type: Boolean, default: false },
});

const emit = defineEmits(['update', 'reset', 'sector']);

/** Fənn siyahısı bundan sonra "Daha çox" arxasında gizlənir */
const SUBJECT_LIMIT = 6;

const open = ref(false);
const openCategory = ref(null);
const allSubjects = ref(false);

const has = (facet) => props.facets.includes(facet);

const list = (key) => props.options[key] ?? [];

const active = computed(
    () => Object.values(props.filters).filter((value) => value !== null && value !== '').length,
);

// Rüb yalnız mövzu sınağı seçiləndə mənalıdır — backend də eyni qaydanı tətbiq edir
const showQuarters = computed(
    () => has('rub') && props.filters.nov === 'topic_trial' && list('quarters').length > 0,
);

const subjects = computed(() => (allSubjects.value
    ? list('subjects')
    : list('subjects').slice(0, SUBJECT_LIMIT)));

const hiddenSubjects = computed(() => Math.max(0, list('subjects').length - SUBJECT_LIMIT));

/*
 * Sinif etiketləri ayrıca göstərilir: kataloqda ən çox işlənən filtr onlardır.
 *
 * Siyahını SERVER süzür: adı sinif bildirən kateqoriyanın səhifəsində ("9-cu sinif
 * buraxılış") sinif etiketləri ümumiyyətlə gəlmir, ona görə bölmə də çıxmır.
 */
const grades = computed(() => list('tags').filter((tag) => tag.kind === 'grade'));
const otherTags = computed(() => list('tags').filter((tag) => tag.kind !== 'grade'));

/** Seçilmiş filtrlərin oxunaqlı adları — silinə bilən çiplər üçün */
const chosen = computed(() => {
    const rows = [];

    const label = (key, options, value) => options.find((option) => option.value === value);

    if (props.filters.kateqoriya) {
        const flat = list('categories').flatMap((root) => [root, ...(root.children ?? [])]);
        const found = label('kateqoriya', flat, props.filters.kateqoriya);

        if (found) {
            rows.push({ key: 'kateqoriya', name: found.name });
        }
    }

    if (props.filters.nov) {
        rows.push({ key: 'nov', name: trans(`category_page.kinds.${props.filters.nov}`) });
    }

    if (props.filters.rub) {
        rows.push({ key: 'rub', name: trans('category_page.quarter', { number: props.filters.rub }) });
    }

    if (props.filters.fenn) {
        const found = label('fenn', list('subjects'), props.filters.fenn);

        if (found) {
            rows.push({ key: 'fenn', name: found.name });
        }
    }

    if (props.filters.etiket) {
        const found = label('etiket', list('tags'), props.filters.etiket);

        if (found) {
            rows.push({ key: 'etiket', name: found.name });
        }
    }

    if (props.filters.qiymet) {
        rows.push({ key: 'qiymet', name: trans(`category_page.prices.${props.filters.qiymet}`) });
    }

    if (props.filters.axtar) {
        rows.push({ key: 'axtar', name: `“${props.filters.axtar}”` });
    }

    return rows;
});

const visible = computed(() => has('sektor') && props.canSwitchSector
    || has('etiket') && list('tags').length > 0
    || has('kateqoriya') && list('categories').length > 1
    || has('nov') && list('kinds').length > 1
    || has('fenn') && list('subjects').length > 1
    || has('qiymet') && list('prices').length > 1);
</script>

<template>
    <section v-if="visible" class="filters-box" :class="`filters-box--${layout}`">
        <!-- Seçilmiş filtrlər: paneldən kənarda, həmişə görünür -->
        <div v-if="chosen.length" class="chosen">
            <span class="chosen-label">{{ $t('exam_catalog.selected') }}:</span>
            <button
                v-for="item in chosen"
                :key="item.key"
                type="button"
                class="chosen-chip"
                :aria-label="$t('exam_catalog.remove', { name: item.name })"
                @click="emit('update', item.key, null)"
            >
                {{ item.name }}<span class="chosen-x" aria-hidden="true">×</span>
            </button>
            <button type="button" class="chosen-reset" @click="emit('reset')">
                {{ $t('exam_catalog.reset_all') }}
            </button>
        </div>

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

            <!-- İmtahanın dili: başlıqdakı AZ|RU interfeys dilidir, bu isə məzmunun dili -->
            <div v-if="has('sektor') && canSwitchSector" class="filter filter--sector">
                <span id="f-sector" class="filter-label">{{ $t('exam_catalog.sector_label') }}</span>
                <div class="chips" role="group" aria-labelledby="f-sector">
                    <button
                        type="button"
                        class="chip"
                        :class="{ 'chip--on': sector === 'az' }"
                        :aria-pressed="sector === 'az'"
                        @click="emit('sector', 'az')"
                    >{{ $t('category_page.sector_az') }}</button>
                    <button
                        type="button"
                        class="chip"
                        :class="{ 'chip--on': sector === 'ru' }"
                        :aria-pressed="sector === 'ru'"
                        @click="emit('sector', 'ru')"
                    >{{ $t('category_page.sector_ru') }}</button>
                </div>
                <p class="filter-hint">{{ $t('exam_catalog.sector_hint') }}</p>
            </div>

            <!-- Kateqoriya: ikisəviyyəli akkordeon -->
            <div v-if="has('kateqoriya') && list('categories').length > 1" class="filter">
                <span class="filter-label">{{ $t('exam_catalog.filter_category') }}</span>
                <ul class="tree">
                    <li v-for="root in list('categories')" :key="root.value" class="tree-node">
                        <div class="tree-row">
                            <button
                                type="button"
                                class="tree-name"
                                :class="{ 'tree-name--on': filters.kateqoriya === root.value }"
                                :aria-pressed="filters.kateqoriya === root.value"
                                :style="{ '--cat': root.color || 'var(--muted)' }"
                                @click="emit('update', 'kateqoriya', root.value)"
                            >
                                <span class="tree-dot" aria-hidden="true"></span>
                                {{ root.name }} <span class="tree-count">({{ root.count }})</span>
                            </button>
                            <button
                                v-if="root.children.length"
                                type="button"
                                class="tree-expand"
                                :aria-expanded="openCategory === root.value"
                                :aria-label="root.name"
                                @click="openCategory = openCategory === root.value ? null : root.value"
                            >
                                <span aria-hidden="true">{{ openCategory === root.value ? '−' : '+' }}</span>
                            </button>
                        </div>
                        <ul v-if="openCategory === root.value" class="tree-children">
                            <li v-for="child in root.children" :key="child.value">
                                <button
                                    type="button"
                                    class="tree-child"
                                    :class="{ 'tree-child--on': filters.kateqoriya === child.value }"
                                    :aria-pressed="filters.kateqoriya === child.value"
                                    @click="emit('update', 'kateqoriya', child.value)"
                                >{{ child.name }} <span class="tree-count">({{ child.count }})</span></button>
                            </li>
                        </ul>
                    </li>
                </ul>
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
                        :class="[`chip--kind-${option.value}`, { 'chip--on': filters.nov === option.value }]"
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
                    >{{ $t('category_page.quarter', { number: option.value }) }} ({{ option.count }})</button>
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
                        v-for="option in subjects"
                        :key="option.value"
                        type="button"
                        class="chip"
                        :class="{ 'chip--on': filters.fenn === option.value }"
                        :aria-pressed="filters.fenn === option.value"
                        @click="emit('update', 'fenn', option.value)"
                    >{{ option.name }} ({{ option.count }})</button>
                    <button
                        v-if="hiddenSubjects"
                        type="button"
                        class="chip chip--more"
                        :aria-expanded="allSubjects"
                        @click="allSubjects = !allSubjects"
                    >{{ allSubjects ? $t('exam_catalog.less') : $t('exam_catalog.more', { count: hiddenSubjects }) }}</button>
                </div>
            </div>

            <!--
                Etiketlər: sinif səviyyəsi ayrıca qrupdur (kataloqda ən çox işlənən filtr),
                sərbəst etiketlər onun altında.
            -->
            <div v-if="has('etiket') && grades.length" class="filter">
                <span id="f-grade" class="filter-label">{{ $t('exam_catalog.filter_grade') }}</span>
                <div class="chips" role="group" aria-labelledby="f-grade">
                    <button
                        type="button"
                        class="chip"
                        :class="{ 'chip--on': !filters.etiket }"
                        :aria-pressed="!filters.etiket"
                        @click="emit('update', 'etiket', null)"
                    >{{ $t('category_page.filter_all') }}</button>
                    <button
                        v-for="option in grades"
                        :key="option.value"
                        type="button"
                        class="chip chip--grade"
                        :class="{ 'chip--on': filters.etiket === option.value }"
                        :aria-pressed="filters.etiket === option.value"
                        @click="emit('update', 'etiket', option.value)"
                    >{{ option.name }} ({{ option.count }})</button>
                </div>
                <p class="filter-hint">{{ $t('exam_catalog.filter_grade_hint') }}</p>
            </div>

            <div v-if="has('etiket') && otherTags.length" class="filter">
                <span id="f-tag" class="filter-label">{{ $t('exam_catalog.filter_tag') }}</span>
                <div class="chips" role="group" aria-labelledby="f-tag">
                    <button
                        v-for="option in otherTags"
                        :key="option.value"
                        type="button"
                        class="chip"
                        :class="{ 'chip--on': filters.etiket === option.value }"
                        :aria-pressed="filters.etiket === option.value"
                        @click="emit('update', 'etiket', filters.etiket === option.value ? null : option.value)"
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
                        :class="[{ 'chip--on': filters.qiymet === option.value }, option.value === 'pulsuz' ? 'chip--free' : '']"
                        :aria-pressed="filters.qiymet === option.value"
                        @click="emit('update', 'qiymet', option.value)"
                    >{{ $t(`category_page.prices.${option.value}`) }} ({{ option.count }})</button>
                </div>
            </div>
        </div>
    </section>
</template>

<style scoped>
.filters-box {
    margin-bottom: 20px;
}

/* ----------------------------------------------------- seçilmiş filtrlər */

.chosen {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 8px;
    margin-bottom: 12px;
}

.chosen-label {
    font-size: 0.875rem;
    color: var(--muted);
}

.chosen-chip {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    min-height: 36px;
    max-width: 100%;
    padding: 6px 10px 6px 12px;
    border: 1px solid var(--pen);
    border-radius: 999px;
    background: #E9ECF6;
    color: var(--pen-deep);
    font: inherit;
    font-size: 0.875rem;
    font-weight: 600;
    overflow-wrap: anywhere;
    cursor: pointer;
}

.chosen-x {
    font-size: 1.05rem;
    line-height: 1;
}

.chosen-reset {
    min-height: 36px;
    padding: 6px 4px;
    border: 0;
    background: none;
    font: inherit;
    font-size: 0.875rem;
    color: var(--ink-red);
    text-decoration: underline;
    cursor: pointer;
}

/* ------------------------------------------------------------- panel */

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
    gap: 16px;
    margin-top: 14px;
}

.filters--open {
    display: grid;
}

.filters-title {
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
    font-weight: 600;
    color: var(--graphite);
}

.filter-hint {
    margin: 0;
    font-size: 0.8125rem;
    color: var(--muted);
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
    color: var(--graphite);
    cursor: pointer;
}

.chip:hover {
    border-color: rgba(22, 19, 14, 0.5);
}

.chip--on {
    border-color: var(--graphite);
    background: var(--graphite);
    color: var(--paper);
    font-weight: 600;
}

/* Növ çipləri rəngli kənarla tanınır; seçiləndə dolu olur (rəng tək göstərici deyil) */
.chip--kind-general { border-left: 4px solid var(--graphite); }
.chip--kind-topic_trial { border-left: 4px solid var(--pen); }
.chip--kind-subject { border-left: 4px solid #6B3FA0; }
.chip--kind-practice { border-left: 4px solid #0F766E; }
.chip--free { border-left: 4px solid var(--correct); }
.chip--grade { border-left: 4px solid var(--pen); }

.chip--more {
    border-style: dashed;
    color: var(--pen);
}

/* -------------------------------------------------- kateqoriya akkordeonu */

.tree {
    list-style: none;
    margin: 0;
    padding: 0;
    display: grid;
    gap: 2px;
}

.tree-row {
    display: flex;
    align-items: stretch;
    gap: 4px;
}

.tree-name {
    display: flex;
    align-items: center;
    gap: 8px;
    flex: 1 1 auto;
    min-width: 0;
    min-height: 44px;
    padding: 8px 10px;
    border: 0;
    border-radius: 8px;
    background: none;
    font: inherit;
    font-size: 0.95rem;
    text-align: left;
    color: var(--graphite);
    cursor: pointer;
}

.tree-name:hover {
    background: var(--paper-sunk);
}

.tree-name--on {
    background: var(--paper-sunk);
    font-weight: 700;
    box-shadow: inset 3px 0 0 var(--cat);
}

.tree-dot {
    width: 8px;
    height: 8px;
    flex: none;
    border-radius: 50%;
    background: var(--cat);
}

.tree-count {
    color: var(--muted);
    font-weight: 400;
}

.tree-expand {
    width: 44px;
    min-height: 44px;
    flex: none;
    border: 0;
    border-radius: 8px;
    background: none;
    font: inherit;
    font-size: 1.15rem;
    color: var(--muted);
    cursor: pointer;
}

.tree-expand:hover {
    background: var(--paper-sunk);
}

.tree-children {
    list-style: none;
    margin: 2px 0 6px;
    padding: 0 0 0 18px;
    display: grid;
    gap: 2px;
    border-left: 1px dashed var(--ink-red-line);
}

.tree-child {
    display: block;
    width: 100%;
    min-height: 44px;
    padding: 8px 10px;
    border: 0;
    border-radius: 8px;
    background: none;
    font: inherit;
    font-size: 0.9375rem;
    text-align: left;
    color: var(--graphite);
    cursor: pointer;
}

.tree-child:hover {
    background: var(--paper-sunk);
}

.tree-child--on {
    background: var(--paper-sunk);
    font-weight: 700;
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
}

/* Yan sütun: başlıq görünür, ölçülər alt-alta düzülür */
@media (min-width: 1024px) {
    .filters-box--side .filters-title {
        position: static;
        width: auto;
        height: auto;
        clip: auto;
        margin: 0 0 4px;
        font-size: 1.05rem;
    }
}
</style>

<script setup>
import { computed, ref, watch } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import SiteHeader from '@/Components/Site/SiteHeader.vue';
import SiteFooter from '@/Components/Site/SiteFooter.vue';
import SeoHead from '@/Components/Site/SeoHead.vue';
import CatalogFilters from '@/Components/Catalog/CatalogFilters.vue';
import ExamCard from '@/Components/Catalog/ExamCard.vue';

/**
 * Ümumi kataloq: `/imtahanlar`.
 *
 * İki görünüş: filtr/axtarış seçilməyibsə bölmələr üzrə qruplaşdırılmış (`grouped`),
 * seçiləndə isə səhifələnən düz siyahı (`list`). SIRALAMA görünüşü dəyişmir — yalnız
 * bölmələrin içindəki sıranı dəyişir, çünki sıralamaya görə düz siyahıya keçmək
 * istifadəçinin gözlədiyi davranış deyil.
 *
 * Bütün seçimlər URL query-də qalır: `?kateqoriya=8&nov=general&sirala=ucuz&sehife=2`.
 * Canonical həmişə filtrsiz `/imtahanlar`-a göstərir (backend).
 */
const props = defineProps({
    mode: { type: String, default: 'grouped' },
    groups: { type: Array, default: () => [] },
    exams: { type: Array, default: () => [] },
    pagination: { type: Object, default: null },
    filterOptions: { type: Object, default: () => ({}) },
    filters: { type: Object, default: () => ({}) },
    sort: { type: String, default: 'yeni' },
    sorts: { type: Array, default: () => [] },
    sector: { type: String, default: 'az' },
    canSwitchSector: { type: Boolean, default: false },
    meta: { type: Object, default: () => ({}) },
});

const SEARCH_MIN = 2;

const search = ref(props.filters.axtar ?? '');

// Serverdən gələn dəyər dəyişəndə (geri düymələri, filtr sıfırlama) sahə də yenilənir
watch(() => props.filters.axtar, (value) => {
    search.value = value ?? '';
});

const go = (changes) => {
    const next = { ...props.filters, sirala: props.sort, ...changes };

    // Rüb yalnız mövzu sınağı üçün mənalıdır
    if ('nov' in changes && changes.nov !== 'topic_trial') {
        next.rub = null;
    }

    // Defolt sıralama ünvanı doldurmasın
    if (next.sirala === 'yeni') {
        next.sirala = null;
    }

    // Filtr dəyişəndə həmişə birinci səhifəyə qayıdılır
    delete next.sehife;

    router.get(
        window.location.pathname,
        Object.fromEntries(Object.entries(next).filter(([, value]) => value !== null && value !== '')),
        { preserveState: true, preserveScroll: true, replace: true },
    );
};

// Yazarkən hər hərfdə sorğu getməsin
let searchTimer = null;

const onSearch = () => {
    clearTimeout(searchTimer);

    searchTimer = setTimeout(() => {
        const value = search.value.trim();

        if (value.length >= SEARCH_MIN) {
            go({ axtar: value });
        } else if (props.filters.axtar) {
            go({ axtar: null });
        }
    }, 350);
};

const clearSearch = () => {
    search.value = '';
    clearTimeout(searchTimer);
    go({ axtar: null });
};

const reset = () => {
    search.value = '';
    router.get(window.location.pathname, {}, { preserveScroll: true, replace: true });
};

const switchSector = (value) => {
    if (value !== props.sector) {
        router.post(route('sector.update'), { sector: value }, { preserveScroll: true });
    }
};

const total = computed(() => (props.mode === 'grouped'
    ? props.groups.reduce((sum, group) => sum + group.total, 0)
    : props.pagination?.total ?? 0));
</script>

<template>
    <SeoHead :title="meta.title" :description="meta.description" />

    <div class="site">
        <SiteHeader />

        <main class="wrap page">
            <h1 class="title">{{ $t('exam_catalog.title') }}</h1>
            <p class="lead">{{ $t('exam_catalog.lead') }}</p>

            <form class="search" role="search" @submit.prevent="onSearch">
                <label class="search-label" for="catalog-search">{{ $t('exam_catalog.search_label') }}</label>
                <div class="search-row">
                    <input
                        id="catalog-search"
                        v-model="search"
                        type="search"
                        class="search-input"
                        :placeholder="$t('exam_catalog.search_placeholder')"
                        autocomplete="off"
                        @input="onSearch"
                    />
                    <button v-if="search" type="button" class="search-clear" @click="clearSearch">
                        {{ $t('exam_catalog.search_clear') }}
                    </button>
                </div>
            </form>

            <div class="layout">
                <CatalogFilters
                    class="layout-filters"
                    :facets="['sektor', 'kateqoriya', 'nov', 'rub', 'fenn', 'qiymet']"
                    :options="filterOptions"
                    :filters="filters"
                    :sector="sector"
                    :can-switch-sector="canSwitchSector"
                    layout="side"
                    @update="(key, value) => go({ [key]: value })"
                    @reset="reset"
                    @sector="switchSector"
                />

                <div class="layout-results">
                    <div class="bar">
                        <p class="found" aria-live="polite">
                            {{ $t('exam_catalog.found', { count: total }) }}
                        </p>
                        <label class="sort">
                            <span class="sort-label">{{ $t('exam_catalog.sort_label') }}</span>
                            <select
                                class="sort-select"
                                :value="sort"
                                @change="go({ sirala: $event.target.value })"
                            >
                                <option v-for="option in sorts" :key="option" :value="option">
                                    {{ $t(`exam_catalog.sorts.${option}`) }}
                                </option>
                            </select>
                        </label>
                    </div>

                    <!-- Defolt görünüş: kök bölmələr üzrə qruplar -->
                    <template v-if="mode === 'grouped'">
                        <section
                            v-for="group in groups"
                            :key="group.id"
                            class="group"
                            :style="{ '--cat': group.color || 'var(--muted)' }"
                        >
                            <div class="group-head">
                                <h2 class="group-title">
                                    <span class="group-dot" aria-hidden="true"></span>{{ group.name }}
                                </h2>
                                <Link :href="group.url" class="group-all">
                                    {{ $t('exam_catalog.show_all', { count: group.total }) }}
                                </Link>
                            </div>
                            <p v-if="group.short" class="group-short">{{ group.short }}</p>

                            <ul class="cards">
                                <li v-for="exam in group.exams" :key="exam.id">
                                    <ExamCard :exam="exam" />
                                </li>
                            </ul>
                        </section>

                        <p v-if="!groups.length" class="empty">{{ $t('exam_catalog.empty') }}</p>
                    </template>

                    <!-- Filtr və ya axtarış seçiləndə: düz siyahı -->
                    <template v-else>
                        <ul v-if="exams.length" class="cards">
                            <li v-for="exam in exams" :key="exam.id">
                                <ExamCard :exam="exam" />
                            </li>
                        </ul>
                        <div v-else class="empty-box">
                            <p class="empty">{{ $t('exam_catalog.empty') }}</p>
                            <button type="button" class="link-button" @click="reset">
                                {{ $t('exam_catalog.reset_all') }}
                            </button>
                        </div>

                        <nav v-if="pagination && pagination.pages > 1" class="pager" :aria-label="$t('exam_catalog.pagination')">
                            <Link
                                v-if="pagination.prev"
                                :href="pagination.prev"
                                class="pager-link"
                                rel="prev"
                                preserve-scroll
                            >{{ $t('exam_catalog.prev') }}</Link>
                            <span v-else class="pager-link pager-link--off" aria-hidden="true">{{ $t('exam_catalog.prev') }}</span>

                            <span class="pager-state">
                                {{ $t('exam_catalog.page_of', { page: pagination.page, pages: pagination.pages }) }}
                            </span>

                            <Link
                                v-if="pagination.next"
                                :href="pagination.next"
                                class="pager-link"
                                rel="next"
                                preserve-scroll
                            >{{ $t('exam_catalog.next') }}</Link>
                            <span v-else class="pager-link pager-link--off" aria-hidden="true">{{ $t('exam_catalog.next') }}</span>
                        </nav>
                    </template>
                </div>
            </div>
        </main>

        <SiteFooter />
    </div>
</template>

<style scoped>
.page {
    --filters-top: 24px;

    padding-block: 32px 72px;
}

/* İyerarxiya: h1 → bölmə başlığı → kart başlığı → meta */
.title {
    margin: 0 0 8px;
    font-size: 1.75rem;
    font-weight: 700;
    letter-spacing: -0.01em;
}

.lead {
    margin: 0;
    max-width: 62ch;
    font-size: 1.05rem;
    color: var(--muted);
}

.search {
    margin-top: 22px;
}

.search-label {
    display: block;
    margin-bottom: 6px;
    font-size: 0.9375rem;
    font-weight: 600;
}

.search-row {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

/*
 * 1rem MƏCBURİDİR: iOS Safari 16px-dən kiçik daxiletmə sahəsində səhifəni avtomatik
 * yaxınlaşdırır və istifadəçi əl ilə geri qaytarmalı olur.
 */
.search-input {
    flex: 1 1 220px;
    min-width: 0;
    min-height: 44px;
    padding: 10px 14px;
    border: 1px solid rgba(22, 19, 14, 0.25);
    border-radius: 10px;
    background: var(--paper);
    font: inherit;
    font-size: 1rem;
    color: inherit;
}

.search-clear {
    min-height: 44px;
    padding: 10px 16px;
    border: 1px solid rgba(22, 19, 14, 0.25);
    border-radius: 10px;
    background: none;
    font: inherit;
    font-size: 0.95rem;
    cursor: pointer;
}

.layout {
    margin-top: 24px;
    display: grid;
    gap: 24px;
}

.layout-results {
    min-width: 0;
}

.bar {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 10px 16px;
    margin-bottom: 18px;
    padding-bottom: 12px;
    border-bottom: 1px dashed var(--ink-red-line);
}

.found {
    margin: 0;
    font-size: 0.9375rem;
    color: var(--muted);
}

.sort {
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.sort-label {
    font-size: 0.9375rem;
    color: var(--muted);
}

/* Select-də 1rem: iOS avtomatik zoom etməsin */
.sort-select {
    min-height: 44px;
    padding: 8px 32px 8px 12px;
    border: 1px solid rgba(22, 19, 14, 0.25);
    border-radius: 10px;
    background: var(--paper);
    font: inherit;
    font-size: 1rem;
    color: var(--graphite);
    cursor: pointer;
}

/* ----------------------------------------------------------- bölmələr */

.group {
    margin-bottom: 36px;
}

.group-head {
    display: flex;
    flex-wrap: wrap;
    align-items: baseline;
    justify-content: space-between;
    gap: 6px 16px;
}

.group-title {
    display: flex;
    align-items: center;
    gap: 8px;
    margin: 0;
    min-width: 0;
    font-size: 1.25rem;
    font-weight: 700;
}

.group-dot {
    width: 10px;
    height: 10px;
    flex: none;
    border-radius: 50%;
    background: var(--cat);
}

.group-all {
    flex: none;
    font-size: 0.9375rem;
    font-weight: 600;
    color: var(--pen);
    text-underline-offset: 4px;
}

.group-short {
    margin: 4px 0 0;
    font-size: 0.9375rem;
    color: var(--muted);
}

.cards {
    list-style: none;
    margin: 14px 0 0;
    padding: 0;
    display: grid;
    gap: 12px;
    grid-template-columns: 1fr;
}

.cards > li {
    min-width: 0;
}

.empty,
.empty-box {
    margin: 24px 0;
}

.empty {
    color: var(--muted);
}

.link-button {
    min-height: 44px;
    padding: 10px 4px;
    border: 0;
    background: none;
    font: inherit;
    color: var(--pen);
    text-decoration: underline;
    cursor: pointer;
}

.pager {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 12px;
    margin-top: 28px;
}

.pager-link {
    display: inline-flex;
    align-items: center;
    min-height: 44px;
    padding: 10px 16px;
    border: 1px solid rgba(22, 19, 14, 0.25);
    border-radius: 999px;
    text-decoration: none;
    font-size: 0.95rem;
}

.pager-link--off {
    opacity: 0.35;
}

.pager-state {
    font-size: 0.9375rem;
    color: var(--muted);
}

@media (min-width: 640px) {
    .cards { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .found,
    .sort-label { font-size: 0.9rem; }
}

@media (min-width: 1024px) {
    /* Masaüstü: filtrlər yan sütunda, nəticələr üç sütunda */
    .layout {
        grid-template-columns: 272px minmax(0, 1fr);
        gap: 32px;
        align-items: start;
    }

    /*
     * Panel səhifə ilə birlikdə sürüşməsin: yerində qalır və UZUNDURSA ÖZ DAXİLİNDƏ sürüşür.
     * Əks halda uzun filtr siyahısı ekrandan çıxır və istifadəçi filtrə çatmaq üçün bütün
     * nəticə siyahısını aşağı sürüşdürməli olurdu.
     *
     * Başlıq `position: fixed` deyil (səhifə ilə birlikdə yuxarı gedir), ona görə yuxarı
     * boşluq sabit deyil, sadəcə nəfəs payıdır.
     */
    .layout-filters {
        position: sticky;
        top: var(--filters-top);
        max-height: calc(100vh - var(--filters-top) * 2);
        overflow-y: auto;
        /* Sürüşmə paneldə qalsın, arxadakı səhifəyə keçməsin */
        overscroll-behavior: contain;
        /* Daxili sürüşmə zolağı çipləri kəsməsin */
        padding-right: 4px;
        margin-bottom: 0;
    }

    .title { font-size: 2rem; }
    .cards { grid-template-columns: repeat(3, minmax(0, 1fr)); }
}
</style>

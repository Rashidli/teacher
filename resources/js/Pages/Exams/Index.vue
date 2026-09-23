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
 * Bütün dərc olunmuş imtahanlar ən yenisindən başlayaraq göstərilir. Seçim URL-də query
 * kimi qalır (`?kateqoriya=8&nov=general&axtar=…&sehife=2`), ona görə süzülmüş səhifə
 * paylaşıla bilir. Canonical həmişə filtrsiz `/imtahanlar`-a göstərir (backend).
 */
const props = defineProps({
    exams: { type: Array, default: () => [] },
    pagination: { type: Object, default: () => ({ page: 1, pages: 1, total: 0, prev: null, next: null }) },
    filterOptions: { type: Object, default: () => ({}) },
    filters: { type: Object, default: () => ({}) },
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
    const next = { ...props.filters, ...changes };

    // Rüb yalnız mövzu sınağı üçün mənalıdır
    if ('nov' in changes && changes.nov !== 'topic_trial') {
        next.rub = null;
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

const reset = () => router.get(window.location.pathname, {}, { preserveScroll: true, replace: true });

const switchSector = (value) => {
    if (value !== props.sector) {
        router.post(route('sector.update'), { sector: value }, { preserveScroll: true });
    }
};

const hasFilters = computed(
    () => Object.entries(props.filters).some(([key, value]) => key !== 'axtar' && value !== null && value !== ''),
);
</script>

<template>
    <SeoHead :title="meta.title" :description="meta.description" />

    <div class="site">
        <SiteHeader />

        <main class="wrap page">
            <h1 class="title">{{ $t('exam_catalog.title') }}</h1>
            <p class="lead">{{ $t('exam_catalog.lead') }}</p>

            <div v-if="canSwitchSector" class="sector" role="group" :aria-label="$t('category_page.sector_switch')">
                <span class="sector-label">{{ $t('category_page.sector_switch') }}:</span>
                <button
                    type="button"
                    class="sector-option"
                    :class="{ 'sector-option--on': sector === 'az' }"
                    :aria-pressed="sector === 'az'"
                    @click="switchSector('az')"
                >{{ $t('category_page.sector_az') }}</button>
                <button
                    type="button"
                    class="sector-option"
                    :class="{ 'sector-option--on': sector === 'ru' }"
                    :aria-pressed="sector === 'ru'"
                    @click="switchSector('ru')"
                >{{ $t('category_page.sector_ru') }}</button>
            </div>

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
                <p class="search-hint">{{ $t('exam_catalog.search_hint', { min: SEARCH_MIN }) }}</p>
            </form>

            <div class="layout">
                <CatalogFilters
                    class="layout-filters"
                    :facets="['kateqoriya', 'nov', 'rub', 'fenn', 'qiymet']"
                    :options="filterOptions"
                    :filters="filters"
                    layout="side"
                    @update="(key, value) => go({ [key]: value })"
                    @reset="reset"
                />

                <div class="layout-results">
                    <p class="found" aria-live="polite">
                        {{ $t('exam_catalog.found', { count: pagination.total }) }}
                    </p>

                    <ul v-if="exams.length" class="cards">
                        <li v-for="exam in exams" :key="exam.id">
                            <ExamCard :exam="exam" show-category />
                        </li>
                    </ul>
                    <p v-else class="empty">{{ $t('exam_catalog.empty') }}</p>

                    <nav v-if="pagination.pages > 1" class="pager" :aria-label="$t('exam_catalog.pagination')">
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
                </div>
            </div>

            <div v-if="!exams.length && hasFilters" class="actions">
                <button type="button" class="link-button" @click="reset">{{ $t('exam_catalog.reset') }}</button>
            </div>
        </main>

        <SiteFooter />
    </div>
</template>

<style scoped>
.page {
    padding-block: 32px 72px;
}

.title {
    margin: 0 0 8px;
}

.lead {
    margin: 0 0 8px;
    max-width: 65ch;
    font-size: 1.1rem;
}

.sector {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 8px;
    margin-top: 14px;
    font-size: 0.9375rem;
}

.sector-label {
    opacity: 0.75;
}

/* Toxunma sahəsi 44px, mətn ölçüsü dəyişmir */
.sector-option {
    min-height: 44px;
    padding: 10px 16px;
    border: 1px solid rgba(22, 19, 14, 0.25);
    border-radius: 999px;
    background: none;
    font: inherit;
    font-size: 0.95rem;
    cursor: pointer;
}

.sector-option--on {
    border-color: rgba(22, 19, 14, 0.7);
    font-weight: 600;
}

.search {
    margin-top: 24px;
}

.search-label {
    display: block;
    margin-bottom: 6px;
    font-size: 0.9375rem;
    opacity: 0.75;
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

.search-hint {
    margin: 6px 0 0;
    font-size: 0.875rem;
    opacity: 0.65;
}

.layout {
    margin-top: 28px;
    display: grid;
    gap: 24px;
}

.layout-results {
    min-width: 0;
}

.found {
    margin: 0 0 14px;
    font-size: 0.9375rem;
    opacity: 0.75;
}

/* Mobildə tək sütun; sonra iki, sonra üç */
.cards {
    list-style: none;
    margin: 0;
    padding: 0;
    display: grid;
    gap: 12px;
    grid-template-columns: 1fr;
}

.cards > li {
    min-width: 0;
}

.empty {
    margin: 24px 0;
    opacity: 0.75;
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
    opacity: 0.75;
}

.actions {
    margin-top: 32px;
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

@media (min-width: 640px) {
    .cards { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .found { font-size: 0.9rem; }
}

@media (min-width: 1024px) {
    /* Masaüstü: filtrlər yan sütunda, nəticələr üç sütunda */
    .layout {
        grid-template-columns: 260px minmax(0, 1fr);
        gap: 32px;
        align-items: start;
    }

    .layout-filters {
        position: sticky;
        top: 24px;
        margin-bottom: 0;
    }

    .cards { grid-template-columns: repeat(3, minmax(0, 1fr)); }
}
</style>

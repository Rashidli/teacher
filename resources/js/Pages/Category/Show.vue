<script setup>
import { computed } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import SiteHeader from '@/Components/Site/SiteHeader.vue';
import SiteFooter from '@/Components/Site/SiteFooter.vue';
import SeoHead from '@/Components/Site/SeoHead.vue';
import { useLocale } from '@/Composables/useLocale';

const props = defineProps({
    category: { type: Object, required: true },
    breadcrumb: { type: Array, default: () => [] },
    children: { type: Array, default: () => [] },
    subjects: { type: Array, default: () => [] },
    exams: { type: Array, default: () => [] },
    // Filtr variantları sayğaclarla (yalnız bu düyündə mövcud olanlar)
    filterOptions: { type: Object, default: () => ({ kinds: [], quarters: [], subjects: [], prices: [] }) },
    // Cari seçim: URL query-dən gəlir və orada qalır
    filters: { type: Object, default: () => ({}) },
    meta: { type: Object, default: () => ({}) },
    // 'topic_trial' → rüb seçimi / rüb imtahanları səhifəsi
    view: { type: String, default: null },
    quarter: { type: Number, default: null },
    quarters: { type: Array, default: () => [] },
    topicTrialUrl: { type: String, default: null },
    hasTopicTrials: { type: Boolean, default: false },
    // Tədris sektoru: qonaq üçün seçilə bilər, daxil olmuş istifadəçidə profildən gəlir
    sector: { type: String, default: 'az' },
    canSwitchSector: { type: Boolean, default: false },
    ruEnabled: { type: Boolean, default: false },
});

const { lroute } = useLocale();

// Filtr seçimi URL-də query kimi saxlanılır ki, süzülmüş səhifə paylaşıla bilsin.
// Boş dəyər açarı URL-dən tamamilə çıxarır.
const applyFilter = (key, value) => {
    const next = { ...props.filters, [key]: value };

    // Rüb yalnız mövzu sınağı üçün mənalıdır
    if (key === 'nov' && value !== 'topic_trial') {
        next.rub = null;
    }

    router.get(
        window.location.pathname,
        Object.fromEntries(Object.entries(next).filter(([, item]) => item !== null && item !== '')),
        { preserveState: true, preserveScroll: true, replace: true },
    );
};

const hasFilters = computed(() => props.filterOptions.kinds.length > 1
    || props.filterOptions.subjects.length > 1
    || props.filterOptions.prices.length > 1);

const activeFilters = computed(() => Object.values(props.filters).filter((item) => item !== null && item !== '').length);

// Seçim sessiyada saxlanılır və qeydiyyat formasında defolt olur (SectorController).
const switchSector = (value) => {
    if (value === props.sector) {
        return;
    }

    router.post(route('sector.update'), { sector: value }, { preserveScroll: true });
};
</script>

<template>
    <SeoHead :title="meta.title" :description="meta.description" />

    <div class="site">
        <SiteHeader />

        <main class="wrap page">
            <nav class="crumb" aria-label="breadcrumb">
                <Link :href="lroute('home')">{{ $t('category_page.home') }}</Link>
                <template v-for="(item, index) in breadcrumb" :key="item.url">
                    <span class="sep" aria-hidden="true">/</span>
                    <Link v-if="index < breadcrumb.length - 1" :href="item.url">{{ item.name }}</Link>
                    <span v-else class="current">{{ item.name }}</span>
                </template>
            </nav>

            <div v-if="canSwitchSector && ruEnabled" class="sector" role="group" :aria-label="$t('category_page.sector_switch')">
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

            <h1 class="title">{{ category.h1 }}</h1>
            <p v-if="category.short" class="lead">{{ category.short }}</p>
            <p v-if="category.intro" class="intro">{{ category.intro }}</p>
            <p v-else-if="category.description" class="intro">{{ category.description }}</p>

            <!-- Mövzu sınağı girişi -->
            <section v-if="!view && hasTopicTrials" class="block" aria-labelledby="trial-title">
                <h2 id="trial-title" class="block-title">{{ $t('category_page.topic_trial') }}</h2>
                <p class="lead">{{ $t('category_page.topic_trial_hint') }}</p>
                <Link :href="topicTrialUrl" class="button">{{ $t('category_page.topic_trial_open') }}</Link>
            </section>

            <!-- Rüb seçimi -->
            <section v-if="view === 'topic_trial' && !quarter" class="block" aria-labelledby="quarters-title">
                <h2 id="quarters-title" class="block-title">{{ $t('category_page.choose_quarter') }}</h2>
                <ul v-if="quarters.length" class="cards">
                    <li v-for="item in quarters" :key="item.quarter">
                        <Link :href="item.url" class="card">
                            <span class="card-name">{{ item.quarter }}-ci rüb</span>
                            <span class="card-short">{{ item.exams }} {{ $t('category_page.exams_count') }}</span>
                        </Link>
                    </li>
                </ul>
                <p v-else class="empty">{{ $t('category_page.no_quarters') }}</p>
            </section>

            <!-- Alt kateqoriyalar -->
            <section v-if="!view && children.length" class="block" aria-labelledby="children-title">
                <h2 id="children-title" class="block-title">{{ $t('category_page.sections') }}</h2>
                <ul class="cards">
                    <li v-for="child in children" :key="child.url">
                        <Link :href="child.url" class="card">
                            <span class="card-name">{{ child.name }}</span>
                            <span v-if="child.short" class="card-short">{{ child.short }}</span>
                        </Link>
                    </li>
                </ul>
            </section>

            <!-- Fənlər və bal -->
            <section v-if="!view && subjects.length" class="block" aria-labelledby="subjects-title">
                <h2 id="subjects-title" class="block-title">{{ $t('category_page.subjects') }}</h2>
                <ul class="subjects">
                    <li v-for="subject in subjects" :key="subject.name">
                        <span class="subject-name">{{ subject.name }}</span>
                        <span class="subject-meta">
                            <template v-if="subject.question_count">
                                {{ subject.question_count }} {{ $t('category_page.questions') }}
                            </template>
                            <template v-if="subject.max_score">
                                · {{ $t('category_page.max_score') }}: {{ subject.max_score }}
                            </template>
                        </span>
                    </li>
                </ul>
            </section>

            <!-- Satışdakı imtahanlar (bu düyün və alt düyünlər) -->
            <section v-if="exams.length || activeFilters" class="block" aria-labelledby="exams-title">
                <h2 id="exams-title" class="block-title">{{ $t('category_page.exams') }}</h2>

                <!-- Filtrlər: seçim URL-də qalır, paylaşıla bilir -->
                <div v-if="hasFilters" class="filters">
                    <div v-if="filterOptions.kinds.length > 1" class="filter" role="group" :aria-label="$t('category_page.filter_kind')">
                        <span class="filter-label">{{ $t('category_page.filter_kind') }}</span>
                        <button
                            type="button"
                            class="chip"
                            :class="{ 'chip--on': !filters.nov }"
                            :aria-pressed="!filters.nov"
                            @click="applyFilter('nov', null)"
                        >{{ $t('category_page.filter_all') }}</button>
                        <button
                            v-for="option in filterOptions.kinds"
                            :key="option.value"
                            type="button"
                            class="chip"
                            :class="{ 'chip--on': filters.nov === option.value }"
                            :aria-pressed="filters.nov === option.value"
                            @click="applyFilter('nov', option.value)"
                        >{{ $t(`category_page.kinds.${option.value}`) }} ({{ option.count }})</button>
                    </div>

                    <div
                        v-if="filters.nov === 'topic_trial' && filterOptions.quarters.length"
                        class="filter"
                        role="group"
                        :aria-label="$t('category_page.filter_quarter')"
                    >
                        <span class="filter-label">{{ $t('category_page.filter_quarter') }}</span>
                        <button
                            type="button"
                            class="chip"
                            :class="{ 'chip--on': !filters.rub }"
                            :aria-pressed="!filters.rub"
                            @click="applyFilter('rub', null)"
                        >{{ $t('category_page.filter_all') }}</button>
                        <button
                            v-for="option in filterOptions.quarters"
                            :key="option.value"
                            type="button"
                            class="chip"
                            :class="{ 'chip--on': filters.rub === option.value }"
                            :aria-pressed="filters.rub === option.value"
                            @click="applyFilter('rub', option.value)"
                        >{{ option.value }}-ci rüb ({{ option.count }})</button>
                    </div>

                    <div v-if="filterOptions.subjects.length > 1" class="filter" role="group" :aria-label="$t('category_page.filter_subject')">
                        <span class="filter-label">{{ $t('category_page.filter_subject') }}</span>
                        <button
                            type="button"
                            class="chip"
                            :class="{ 'chip--on': !filters.fenn }"
                            :aria-pressed="!filters.fenn"
                            @click="applyFilter('fenn', null)"
                        >{{ $t('category_page.filter_all') }}</button>
                        <button
                            v-for="option in filterOptions.subjects"
                            :key="option.value"
                            type="button"
                            class="chip"
                            :class="{ 'chip--on': filters.fenn === option.value }"
                            :aria-pressed="filters.fenn === option.value"
                            @click="applyFilter('fenn', option.value)"
                        >{{ option.name }} ({{ option.count }})</button>
                    </div>

                    <div v-if="filterOptions.prices.length > 1" class="filter" role="group" :aria-label="$t('category_page.filter_price')">
                        <span class="filter-label">{{ $t('category_page.filter_price') }}</span>
                        <button
                            type="button"
                            class="chip"
                            :class="{ 'chip--on': !filters.qiymet }"
                            :aria-pressed="!filters.qiymet"
                            @click="applyFilter('qiymet', null)"
                        >{{ $t('category_page.filter_all') }}</button>
                        <button
                            v-for="option in filterOptions.prices"
                            :key="option.value"
                            type="button"
                            class="chip"
                            :class="{ 'chip--on': filters.qiymet === option.value }"
                            :aria-pressed="filters.qiymet === option.value"
                            @click="applyFilter('qiymet', option.value)"
                        >{{ $t(`category_page.prices.${option.value}`) }} ({{ option.count }})</button>
                    </div>
                </div>

                <ul v-if="exams.length" class="cards">
                    <li v-for="exam in exams" :key="exam.id">
                        <Link :href="exam.url" class="card">
                            <span class="card-name">{{ exam.title }}</span>
                            <span class="card-short">
                                <template v-if="exam.subjects.length">{{ exam.subjects.join(', ') }} · </template>
                                {{ exam.questions_count }} {{ $t('category_page.questions') }}
                                · {{ exam.duration_minutes }} {{ $t('category_page.minutes') }}
                            </span>
                            <span class="card-price">
                                {{ exam.is_free ? $t('category_page.free') : `${exam.price} AZN` }}
                            </span>
                        </Link>
                    </li>
                </ul>
                <p v-else class="empty">{{ $t('category_page.no_match') }}</p>
            </section>

            <p v-if="!children.length && !exams.length && !activeFilters" class="empty">
                {{ category.has_exams ? $t('category_page.lead') : $t('category_page.info_only') }}
            </p>

            <div class="actions">
                <Link :href="lroute('register')" class="button">{{ $t('category_page.register') }}</Link>
                <Link :href="lroute('login')" class="link">{{ $t('category_page.login') }}</Link>
            </div>
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

.sector {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 8px;
    margin-top: 14px;
    font-size: 0.95rem;
}

.sector-label {
    opacity: 0.75;
}

.sector-option {
    min-height: 36px;
    padding: 6px 14px;
    border: 1px solid rgba(22, 19, 14, 0.25);
    border-radius: 999px;
    background: none;
    font: inherit;
    cursor: pointer;
}

.sector-option--on {
    border-color: rgba(22, 19, 14, 0.7);
    font-weight: 600;
}

.title {
    margin: 12px 0 8px;
}

.lead {
    margin: 0 0 8px;
    font-size: 1.1rem;
}

.intro {
    margin: 0 0 8px;
    max-width: 65ch;
    opacity: 0.85;
}

.block {
    margin-top: 40px;
}

.block-title {
    font-size: 1.15rem;
    margin: 0 0 14px;
}

.filters {
    display: grid;
    gap: 10px;
    margin-bottom: 20px;
}

.filter {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 8px;
}

.filter-label {
    font-size: 0.9rem;
    opacity: 0.7;
    min-width: 80px;
}

.chip {
    min-height: 36px;
    padding: 6px 14px;
    border: 1px solid rgba(22, 19, 14, 0.25);
    border-radius: 999px;
    background: none;
    font: inherit;
    font-size: 0.95rem;
    cursor: pointer;
}

.chip--on {
    border-color: rgba(22, 19, 14, 0.7);
    font-weight: 600;
}

.cards {
    list-style: none;
    margin: 0;
    padding: 0;
    display: grid;
    gap: 12px;
    grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
}

.card {
    display: flex;
    flex-direction: column;
    gap: 4px;
    padding: 16px;
    border: 1px solid rgba(22, 19, 14, 0.15);
    border-radius: 12px;
    text-decoration: none;
    height: 100%;
}

.card:hover {
    border-color: rgba(22, 19, 14, 0.4);
}

.card-name {
    font-weight: 600;
}

.card-short {
    font-size: 0.9rem;
    opacity: 0.75;
}

.card-price {
    margin-top: 6px;
    font-weight: 600;
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

.empty {
    margin-top: 32px;
    opacity: 0.75;
}

.actions {
    margin-top: 40px;
    display: flex;
    flex-wrap: wrap;
    gap: 16px;
    align-items: center;
}
</style>

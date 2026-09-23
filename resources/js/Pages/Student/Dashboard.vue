<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PanelCard from '@/Components/Ui/PanelCard.vue';
import PanelButton from '@/Components/Ui/PanelButton.vue';
import { Head, Link } from '@inertiajs/vue3';
import { useLocale } from '@/Composables/useLocale';

/**
 * Şagird paneli.
 *
 * Yeni imtahan axtarışı kataloqdadır (kateqoriya ağacı) — burada tövsiyə bloku yoxdur.
 * Onun yerini P3-dəki "Məqsədim" funksiyası tutacaq.
 *
 * DİZAYN: ictimai tərəflə eyni dil — `:root` tokenləri, `PanelCard`/`PanelButton` ortaq
 * komponentləri, sətirlərdə isə imtahanın BÖLMƏ RƏNGİ (kataloq kartındakı ilə eyni).
 */
defineProps({
    stats: Object,
    inProgress: { type: Array, default: () => [] },
    recentResults: { type: Array, default: () => [] },
});

const { lroute } = useLocale();

const accent = (item) => item.trail?.color || 'var(--muted)';

const trailText = (item) => [item.trail?.root, item.trail?.leaf].filter(Boolean).join(' › ');
</script>

<template>
    <Head title="Şagird Paneli" />

    <AuthenticatedLayout>
        <template #header>
            <div class="head">
                <div>
                    <h1 class="head-title">Şagird paneli</h1>
                    <p class="head-lead">Davam edən imtahanların və son nəticələrin burada.</p>
                </div>
                <PanelButton :href="lroute('exams.catalog')">İmtahan seç</PanelButton>
            </div>
        </template>

        <div class="wrap page">
            <!--
                Göstəricilər sadələşdirildi: əvvəl dörd iri kart vardı və onların biri
                ("Düzgün cavab %") nisbi balla qarışırdı. İndi üç rəqəm bir sətirdədir,
                əsas yeri davam edən imtahanlar və nəticələr tutur.
            -->
            <ul class="stats">
                <li>
                    <span class="stat-value">{{ stats?.totalAttempts || 0 }}</span>
                    <span class="stat-label">verilən imtahan</span>
                </li>
                <li>
                    <span class="stat-value stat-value--pen">{{ stats?.averageScore || 0 }}</span>
                    <span class="stat-label">orta nəticə (100-lük)</span>
                </li>
                <li>
                    <span class="stat-value stat-value--best">{{ stats?.highestScore || 0 }}</span>
                    <span class="stat-label">ən yüksək nəticə</span>
                </li>
            </ul>

            <div class="columns">
                <!-- Davam edən imtahanlar: taymer işlədiyi üçün birinci yerdədir -->
                <PanelCard title="Davam edən imtahanlar" accent="var(--sign-yellow)" flush>
                    <ul v-if="inProgress.length" class="rows">
                        <li v-for="item in inProgress" :key="item.id" :style="{ '--accent': accent(item) }">
                            <div class="row">
                                <div class="row-text">
                                    <p v-if="trailText(item)" class="row-trail">
                                        <span class="row-dot" aria-hidden="true"></span>{{ trailText(item) }}
                                    </p>
                                    <p class="row-title">{{ item.title }}</p>
                                    <p class="row-meta row-meta--urgent">{{ item.remaining_minutes }} dəqiqə qalıb</p>
                                </div>
                                <PanelButton :href="item.url" small>Davam et</PanelButton>
                            </div>
                        </li>
                    </ul>
                    <p v-else class="empty">Davam edən imtahanın yoxdur.</p>
                </PanelCard>

                <!-- Son nəticələr -->
                <PanelCard title="Son nəticələr" accent="var(--correct)" flush>
                    <template #actions>
                        <PanelButton :href="route('student.results.index')" variant="quiet" small>
                            Hamısına bax
                        </PanelButton>
                    </template>

                    <ul v-if="recentResults.length" class="rows">
                        <li v-for="item in recentResults" :key="item.id" :style="{ '--accent': accent(item) }">
                            <Link :href="item.url" class="row row--link">
                                <div class="row-text">
                                    <p v-if="trailText(item)" class="row-trail">
                                        <span class="row-dot" aria-hidden="true"></span>{{ trailText(item) }}
                                    </p>
                                    <p class="row-title">{{ item.title }}</p>
                                    <p class="row-meta">{{ item.finished_at }}</p>
                                </div>
                                <div class="row-score">
                                    <span class="score">{{ item.relative_score ?? 0 }}</span>
                                    <span class="score-max">/ 100</span>
                                    <span class="score-note">{{ item.correct_answers }}/{{ item.question_count }} düz</span>
                                </div>
                            </Link>
                        </li>
                    </ul>
                    <p v-else class="empty">Hələ imtahan verməmisən.</p>
                </PanelCard>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
.head {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 12px 20px;
}

.head-title {
    margin: 0;
    font-family: var(--font-display);
    font-size: 1.5rem;
    font-weight: 700;
    letter-spacing: -0.01em;
    color: var(--graphite);
}

.head-lead {
    margin: 4px 0 0;
    font-size: 0.9375rem;
    color: var(--muted);
}

.page {
    padding-block: 28px 64px;
}

/* ---------------------------------------------------------- göstəricilər */

.stats {
    list-style: none;
    margin: 0 0 24px;
    padding: 14px 18px;
    display: grid;
    gap: 14px 28px;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    border: var(--card-border);
    border-radius: var(--card-radius);
    background: var(--paper);
}

.stats li {
    display: flex;
    flex-direction: column;
    min-width: 0;
}

.stat-value {
    font-family: var(--font-display);
    font-size: 1.75rem;
    font-weight: 700;
    line-height: 1.1;
    color: var(--graphite);
}

.stat-value--pen { color: var(--pen); }
.stat-value--best { color: var(--correct); }

.stat-label {
    margin-top: 2px;
    font-size: 0.8125rem;
    color: var(--muted);
}

/* ---------------------------------------------------------------- sütunlar */

.columns {
    display: grid;
    gap: 20px;
}

/* ---------------------------------------------------------------- sətirlər */

.rows {
    list-style: none;
    margin: 0;
    padding: 0;
}

.rows > li + li {
    border-top: 1px dashed var(--ink-red-line);
}

.row {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 10px 16px;
    /* Bölmə rəngi sol kənarda: kataloq kartındakı zolağın sətir variantı */
    border-left: 3px solid var(--accent);
    padding: 14px 18px;
    text-decoration: none;
}

.row--link {
    color: inherit;
}

.row--link:hover {
    background: var(--paper-sunk);
}

.row-text {
    min-width: 0;
}

.row-trail {
    display: flex;
    align-items: center;
    gap: 6px;
    margin: 0 0 2px;
    font-size: 0.8125rem;
    font-weight: 600;
    color: var(--accent);
}

.row-dot {
    width: 7px;
    height: 7px;
    flex: none;
    border-radius: 50%;
    background: var(--accent);
}

.row-title {
    margin: 0;
    font-weight: 600;
    color: var(--graphite);
}

.row-meta {
    margin: 2px 0 0;
    font-size: 0.875rem;
    color: var(--muted);
}

.row-meta--urgent {
    color: #8A5A05;
    font-weight: 600;
}

.row-score {
    flex: none;
    text-align: right;
}

.score {
    font-family: var(--font-display);
    font-size: 1.25rem;
    font-weight: 700;
    color: var(--graphite);
}

.score-max {
    font-size: 0.875rem;
    color: var(--muted);
}

.score-note {
    display: block;
    font-size: 0.75rem;
    color: var(--muted);
}

.empty {
    margin: 0;
    padding: 18px;
    color: var(--muted);
}

@media (min-width: 1024px) {
    .columns { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .head-title { font-size: 1.75rem; }
}
</style>

<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PanelHead from '@/Components/Ui/PanelHead.vue';
import PanelCard from '@/Components/Ui/PanelCard.vue';
import PanelButton from '@/Components/Ui/PanelButton.vue';
import PanelRow from '@/Components/Ui/PanelRow.vue';
import { Head } from '@inertiajs/vue3';
import { useLocale } from '@/Composables/useLocale';

/**
 * Şagird paneli.
 *
 * Yeni imtahan axtarışı kataloqdadır (kateqoriya ağacı) — burada tövsiyə bloku yoxdur.
 * Onun yerini P3-dəki "Məqsədim" funksiyası tutacaq.
 */
defineProps({
    stats: Object,
    inProgress: { type: Array, default: () => [] },
    recentResults: { type: Array, default: () => [] },
});

const { lroute } = useLocale();
</script>

<template>
    <Head title="Şagird Paneli" />

    <AuthenticatedLayout>
        <template #header>
            <PanelHead title="Şagird paneli" lead="Davam edən imtahanların və son nəticələrin burada.">
                <template #actions>
                    <PanelButton :href="lroute('exams.catalog')">İmtahan seç</PanelButton>
                </template>
            </PanelHead>
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
                        <PanelRow
                            v-for="item in inProgress"
                            :key="item.id"
                            :title="item.title"
                            :trail="item.trail"
                        >
                            <template #meta>
                                <span class="urgent">{{ item.remaining_minutes }} dəqiqə qalıb</span>
                            </template>
                            <template #actions>
                                <PanelButton :href="item.url" small>Davam et</PanelButton>
                            </template>
                        </PanelRow>
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
                        <PanelRow
                            v-for="item in recentResults"
                            :key="item.id"
                            :title="item.title"
                            :trail="item.trail"
                            :href="item.url"
                        >
                            <template #meta>{{ item.finished_at }}</template>
                            <template #actions>
                                <span class="score-block">
                                    <span class="score">{{ item.relative_score ?? 0 }}</span>
                                    <span class="score-max">/ 100</span>
                                    <span class="score-note">{{ item.correct_answers }}/{{ item.question_count }} düz</span>
                                </span>
                            </template>
                        </PanelRow>
                    </ul>
                    <p v-else class="empty">Hələ imtahan verməmisən.</p>
                </PanelCard>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
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

/* ------------------------------------------------------------- sütunlar */

.columns {
    display: grid;
    gap: 20px;
}

.rows {
    list-style: none;
    margin: 0;
    padding: 0;
}

.urgent {
    font-weight: 600;
    color: #8A5A05;
}

.score-block {
    display: block;
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
}
</style>

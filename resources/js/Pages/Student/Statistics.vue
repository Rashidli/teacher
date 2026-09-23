<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PanelHead from '@/Components/Ui/PanelHead.vue';
import PanelCard from '@/Components/Ui/PanelCard.vue';
import PanelButton from '@/Components/Ui/PanelButton.vue';
import LineChart from '@/Components/Charts/LineChart.vue';
import ScoreBar from '@/Components/Charts/ScoreBar.vue';
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    overview: { type: Object, default: () => ({}) },
    subjects: { type: Array, default: () => [] },
    topics: { type: Array, default: () => [] },
    timeline: { type: Array, default: () => [] },
    purchases: { type: Array, default: () => [] },
    minTopicAnswers: { type: Number, default: 3 },
});

const SOURCE_LABELS = { payment: 'Ödəniş', manual: 'Admin icazəsi', free: 'Pulsuz' };

const chartPoints = computed(() => props.timeline.map((item) => ({
    label: item.date ?? '',
    value: item.relative_score ?? 0,
})));

const weakTopics = computed(() => props.topics.filter((topic) => topic.is_weak));
const strongTopics = computed(() => props.topics.filter((topic) => !topic.is_weak).slice(-5).reverse());

const changeLabel = (change) => (change > 0 ? `+${change}` : `${change}`);
</script>

<template>
    <Head title="Statistika" />

    <AuthenticatedLayout>
        <template #header>
            <PanelHead title="Statistika" lead="Fənn üzrə irəliləyiş və mövzu üzrə zəif yerlər.">
                <template #actions>
                    <PanelButton :href="route('student.results.index')" variant="ghost">Bütün nəticələr</PanelButton>
                </template>
            </PanelHead>
        </template>

        <div class="wrap page">
            <div class="stack">
                <!-- Ümumi göstəricilər: bir sətirdə, iri kartlar olmadan -->
                <ul class="stats">
                    <li>
                        <span class="stat-value">{{ overview.attempts ?? 0 }}</span>
                        <span class="stat-label">tamamlanmış cəhd</span>
                    </li>
                    <li>
                        <span class="stat-value stat-value--pen">{{ overview.average_relative ?? 0 }}</span>
                        <span class="stat-label">orta nəticə (100-lük)</span>
                    </li>
                    <li>
                        <span class="stat-value stat-value--best">{{ overview.best_relative ?? 0 }}</span>
                        <span class="stat-label">ən yüksək</span>
                    </li>
                    <li>
                        <span class="stat-value">{{ overview.total_minutes ?? 0 }}</span>
                        <span class="stat-label">dəqiqə imtahanda</span>
                    </li>
                </ul>

                <PanelCard title="Cəhdlər üzrə irəliləyiş" accent="var(--pen)">
                    <LineChart :points="chartPoints" :max="100" label="Nisbi bal (100-lük), son cəhdlər" />
                </PanelCard>

                <PanelCard title="Fənn üzrə irəliləyiş" accent="var(--graphite)">
                    <p v-if="!subjects.length" class="muted">
                        Hələ tamamlanmış imtahan yoxdur.
                    </p>

                    <!-- Cədvəl dar ekranda öz konteynerində sürüşür, səhifəni yana çəkmir -->
                    <div v-else class="scroller">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Fənn</th>
                                <th>Cəhd</th>
                                <th class="col-wide">Orta (100-lük)</th>
                                <th>Ən yüksək</th>
                                <th>Son</th>
                                <th>Dəyişmə</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="subject in subjects" :key="subject.subject">
                                <td class="cell-name">{{ subject.subject }}</td>
                                <td>{{ subject.attempts }}</td>
                                <td><ScoreBar :value="subject.average" /></td>
                                <td>{{ subject.best }}</td>
                                <td>{{ subject.last }}</td>
                                <td>
                                    <span
                                        v-if="subject.change !== null"
                                        :class="subject.change >= 0 ? 'up' : 'down'"
                                    >{{ changeLabel(subject.change) }}</span>
                                    <span v-else class="muted">—</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    </div>
                </PanelCard>

                <!-- Mövzular -->
                <div class="columns">
                    <PanelCard title="Zəif mövzular" accent="var(--ink-red)">
                        <p class="hint">
                            Ən azı {{ minTopicAnswers }} sual cavablandırılmış mövzular, düzgünlük 60%-dən aşağı.
                        </p>

                        <p v-if="!weakTopics.length" class="muted">
                            Zəif mövzu yoxdur — kifayət qədər məlumat toplananda burada görünəcək.
                        </p>

                        <ul v-else class="topics">
                            <li v-for="topic in weakTopics" :key="topic.topic">
                                <div class="topic-line">
                                    <span class="topic-name">{{ topic.topic }}</span>
                                    <span class="muted">{{ topic.correct }}/{{ topic.answered }}</span>
                                </div>
                                <div class="topic-sub">
                                    {{ topic.subject }}<template v-if="topic.topic_group"> · {{ topic.topic_group }}</template>
                                </div>
                                <ScoreBar :value="topic.accuracy" weak class="bar" />
                            </li>
                        </ul>
                    </PanelCard>

                    <PanelCard title="Ən güclü mövzular" accent="var(--correct)">
                        <p v-if="!strongTopics.length" class="muted">Hələ məlumat yoxdur.</p>

                        <ul v-else class="topics">
                            <li v-for="topic in strongTopics" :key="topic.topic">
                                <div class="topic-line">
                                    <span class="topic-name">{{ topic.topic }}</span>
                                    <span class="muted">{{ topic.correct }}/{{ topic.answered }}</span>
                                </div>
                                <ScoreBar :value="topic.accuracy" class="bar" />
                            </li>
                        </ul>
                    </PanelCard>
                </div>

                <!-- Alınmış imtahanlar -->
                <PanelCard title="Giriş hüququm olan imtahanlar" accent="var(--pen)">
                    <p v-if="!purchases.length" class="muted">
                        Hələ imtahan almamısınız.
                        <Link :href="route('student.exams.index')" class="access-title">Mənim imtahanlarım</Link>
                    </p>

                    <ul v-else class="access">
                        <li v-for="item in purchases" :key="`${item.exam_id}-${item.granted_at}`">
                            <div>
                                <Link :href="item.url" class="access-title">{{ item.title }}</Link>
                                <div class="topic-sub">
                                    {{ SOURCE_LABELS[item.source] ?? item.source }} · {{ item.granted_at }}
                                    <template v-if="item.expires_at"> · bitir: {{ item.expires_at }}</template>
                                </div>
                            </div>
                            <span :class="['tag', item.is_active ? 'tag--on' : 'tag--off']">
                                {{ item.is_active ? 'Aktiv' : 'Bitib' }}
                            </span>
                        </li>
                    </ul>
                </PanelCard>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
.page {
    padding-block: 28px 64px;
}

.stack {
    display: grid;
    gap: 20px;
}

.columns {
    display: grid;
    gap: 20px;
}

/* ---------------------------------------------------------- göstəricilər */

.stats {
    list-style: none;
    margin: 0;
    padding: 14px 18px;
    display: grid;
    gap: 14px 28px;
    grid-template-columns: repeat(2, minmax(0, 1fr));
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

/* --------------------------------------------------------------- cədvəl */

/* Dar ekranda cədvəl ÖZ konteynerində sürüşür, səhifə yana çəkilmir */
.scroller {
    overflow-x: auto;
    overscroll-behavior-x: contain;
}

.table {
    width: 100%;
    min-width: 520px;
    border-collapse: collapse;
    font-size: 0.9rem;
}

.table th {
    padding: 6px 10px 10px 0;
    text-align: left;
    font-weight: 600;
    color: var(--muted);
}

.table td {
    padding: 10px 10px 10px 0;
    border-top: 1px dashed var(--ink-red-line);
}

.col-wide { width: 34%; }

.cell-name {
    font-weight: 600;
    color: var(--graphite);
}

.up { color: var(--correct); font-weight: 600; }
.down { color: var(--ink-red); font-weight: 600; }

/* -------------------------------------------------------------- mövzular */

.hint {
    margin: 0 0 12px;
    font-size: 0.8125rem;
    color: var(--muted);
}

.topics {
    list-style: none;
    margin: 0;
    padding: 0;
    display: grid;
    gap: 14px;
}

.topic-line {
    display: flex;
    justify-content: space-between;
    gap: 10px;
    font-size: 0.9375rem;
}

.topic-name {
    font-weight: 600;
    color: var(--graphite);
}

.topic-sub {
    margin-top: 2px;
    font-size: 0.8125rem;
    color: var(--muted);
}

.bar {
    margin-top: 6px;
}

/* --------------------------------------------------------- giriş hüququ */

.access {
    list-style: none;
    margin: 0;
    padding: 0;
}

.access li {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 8px 14px;
    padding: 12px 0;
}

.access li + li {
    border-top: 1px dashed var(--ink-red-line);
}

.access-title {
    font-weight: 600;
    color: var(--pen);
    text-underline-offset: 4px;
}

.tag {
    flex: none;
    padding: 4px 12px;
    border-radius: 999px;
    font-size: 0.8125rem;
    font-weight: 600;
}

.tag--on { background: #E6EFEA; color: var(--correct); }
.tag--off { background: var(--paper-sunk); color: var(--muted); }

.muted {
    color: var(--muted);
    font-size: 0.9375rem;
}

@media (min-width: 720px) {
    .stats { grid-template-columns: repeat(4, minmax(0, 1fr)); }
}

@media (min-width: 1024px) {
    .columns { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}
</style>

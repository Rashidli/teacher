<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
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
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">Statistika</h2>
                <Link :href="route('student.results.index')" class="text-sm text-indigo-600 hover:text-indigo-800">
                    Bütün nəticələr
                </Link>
            </div>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl space-y-8 sm:px-6 lg:px-8">
                <!-- Ümumi -->
                <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4">
                    <div class="rounded-lg bg-white p-6 shadow-sm">
                        <div class="text-sm font-medium text-gray-500">Tamamlanmış cəhd</div>
                        <div class="mt-2 text-3xl font-semibold text-gray-900">{{ overview.attempts ?? 0 }}</div>
                    </div>
                    <div class="rounded-lg bg-white p-6 shadow-sm">
                        <div class="text-sm font-medium text-gray-500">Orta nəticə (100-lük)</div>
                        <div class="mt-2 text-3xl font-semibold text-indigo-600">{{ overview.average_relative ?? 0 }}</div>
                    </div>
                    <div class="rounded-lg bg-white p-6 shadow-sm">
                        <div class="text-sm font-medium text-gray-500">Ən yüksək</div>
                        <div class="mt-2 text-3xl font-semibold text-green-600">{{ overview.best_relative ?? 0 }}</div>
                    </div>
                    <div class="rounded-lg bg-white p-6 shadow-sm">
                        <div class="text-sm font-medium text-gray-500">İmtahanda keçən vaxt</div>
                        <div class="mt-2 text-3xl font-semibold text-gray-900">{{ overview.total_minutes ?? 0 }} dəq</div>
                    </div>
                </div>

                <!-- Cəhdlərin qrafiki -->
                <div class="rounded-lg bg-white p-6 shadow-sm">
                    <h3 class="mb-4 text-lg font-semibold text-gray-900">Cəhdlər üzrə irəliləyiş</h3>
                    <LineChart :points="chartPoints" :max="100" label="Nisbi bal (100-lük), son cəhdlər" />
                </div>

                <!-- Fənn üzrə -->
                <div class="rounded-lg bg-white p-6 shadow-sm">
                    <h3 class="mb-4 text-lg font-semibold text-gray-900">Fənn üzrə irəliləyiş</h3>

                    <p v-if="!subjects.length" class="text-sm text-gray-500">
                        Hələ tamamlanmış imtahan yoxdur.
                    </p>

                    <table v-else class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead>
                            <tr class="text-left text-gray-500">
                                <th class="py-2">Fənn</th>
                                <th class="py-2">Cəhd</th>
                                <th class="py-2 w-1/3">Orta (100-lük)</th>
                                <th class="py-2">Ən yüksək</th>
                                <th class="py-2">Son</th>
                                <th class="py-2">Dəyişmə</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="subject in subjects" :key="subject.subject">
                                <td class="py-2 font-medium text-gray-800">{{ subject.subject }}</td>
                                <td class="py-2">{{ subject.attempts }}</td>
                                <td class="py-2"><ScoreBar :value="subject.average" /></td>
                                <td class="py-2">{{ subject.best }}</td>
                                <td class="py-2">{{ subject.last }}</td>
                                <td class="py-2">
                                    <span
                                        v-if="subject.change !== null"
                                        :class="subject.change >= 0 ? 'text-green-600' : 'text-red-600'"
                                    >{{ changeLabel(subject.change) }}</span>
                                    <span v-else class="text-gray-400">—</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Mövzular -->
                <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                    <div class="rounded-lg bg-white p-6 shadow-sm">
                        <h3 class="mb-1 text-lg font-semibold text-gray-900">Zəif mövzular</h3>
                        <p class="mb-4 text-xs text-gray-500">
                            Ən azı {{ minTopicAnswers }} sual cavablandırılmış mövzular, düzgünlük 60%-dən aşağı.
                        </p>

                        <p v-if="!weakTopics.length" class="text-sm text-gray-500">
                            Zəif mövzu yoxdur — kifayət qədər məlumat toplananda burada görünəcək.
                        </p>

                        <ul v-else class="space-y-3">
                            <li v-for="topic in weakTopics" :key="topic.topic">
                                <div class="flex justify-between text-sm">
                                    <span class="font-medium text-gray-800">{{ topic.topic }}</span>
                                    <span class="text-gray-500">{{ topic.correct }}/{{ topic.answered }}</span>
                                </div>
                                <div class="text-xs text-gray-500">
                                    {{ topic.subject }}<template v-if="topic.topic_group"> · {{ topic.topic_group }}</template>
                                </div>
                                <ScoreBar :value="topic.accuracy" weak class="mt-1" />
                            </li>
                        </ul>
                    </div>

                    <div class="rounded-lg bg-white p-6 shadow-sm">
                        <h3 class="mb-4 text-lg font-semibold text-gray-900">Ən güclü mövzular</h3>

                        <p v-if="!strongTopics.length" class="text-sm text-gray-500">Hələ məlumat yoxdur.</p>

                        <ul v-else class="space-y-3">
                            <li v-for="topic in strongTopics" :key="topic.topic">
                                <div class="flex justify-between text-sm">
                                    <span class="font-medium text-gray-800">{{ topic.topic }}</span>
                                    <span class="text-gray-500">{{ topic.correct }}/{{ topic.answered }}</span>
                                </div>
                                <ScoreBar :value="topic.accuracy" class="mt-1" />
                            </li>
                        </ul>
                    </div>
                </div>

                <!-- Alınmış imtahanlar -->
                <div class="rounded-lg bg-white p-6 shadow-sm">
                    <h3 class="mb-4 text-lg font-semibold text-gray-900">Giriş hüququm olan imtahanlar</h3>

                    <p v-if="!purchases.length" class="text-sm text-gray-500">
                        Hələ imtahan almamısınız.
                        <Link :href="route('student.exams.index')" class="text-indigo-600 hover:text-indigo-800">
                            Kataloqa bax
                        </Link>
                    </p>

                    <ul v-else class="divide-y divide-gray-100">
                        <li v-for="item in purchases" :key="`${item.exam_id}-${item.granted_at}`" class="flex flex-wrap items-center justify-between gap-2 py-3">
                            <div>
                                <Link
                                    :href="item.url"
                                    class="font-medium text-indigo-600 hover:text-indigo-800"
                                >{{ item.title }}</Link>
                                <div class="text-xs text-gray-500">
                                    {{ SOURCE_LABELS[item.source] ?? item.source }} · {{ item.granted_at }}
                                    <template v-if="item.expires_at"> · bitir: {{ item.expires_at }}</template>
                                </div>
                            </div>
                            <span
                                class="rounded-full px-3 py-1 text-xs"
                                :class="item.is_active ? 'bg-green-50 text-green-700' : 'bg-gray-100 text-gray-500'"
                            >{{ item.is_active ? 'Aktiv' : 'Bitib' }}</span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

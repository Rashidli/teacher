<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import MathText from '@/Components/MathText.vue';
import QuestionImage from '@/Components/QuestionImage.vue';
import LineChart from '@/Components/Charts/LineChart.vue';
import ScoreBar from '@/Components/Charts/ScoreBar.vue';
import { computed } from 'vue';

const props = defineProps({
    attempt: Object,
    sections: { type: Array, default: () => [] },
    exam: Object,
    // İctimai imtahan səhifəsi: "Yenidən imtahan ver" ora aparır
    examUrl: { type: String, default: null },
    answers: Array,
    // Eyni imtahanın əvvəlki cəhdləri və bu cəhdin mövzu bölgüsü
    comparison: { type: Object, default: () => ({ history: [], previous: null, change: null }) },
    topics: { type: Array, default: () => [] },
});

const historyPoints = computed(() => (props.comparison.history ?? []).map((item) => ({
    label: item.date ?? '',
    value: item.relative_score ?? 0,
})));

/*
 * Əsas göstərici NİSBİ BALDIR (NB). Əvvəl onun yanında ikinci, fərqli faiz
 * ("13% düzgün") da vardı — iki faiz yan-yana durmasın deyə o, SAYA çevrildi.
 */
const relative = computed(() => Number(props.attempt.relative_score ?? 0));

/*
 * Açıq suallar yoxlanmayıbsa bal İLKİNDİR: rəng neytral qalır, yekun bal yalnız
 * yoxlamadan sonra vurğulanır.
 */
const getScoreColor = computed(() => {
    if (props.attempt.awaiting_review) return 'text-gray-500';
    if (relative.value >= 80) return 'text-green-600';
    if (relative.value >= 60) return 'text-yellow-600';
    return 'text-red-600';
});

// "Qrup: " sətri qrupsuz imtahanlarda boş qalırdı (MİQ, sürücülük — group_id NULL)
const contextLabel = computed(() => props.attempt.group?.name || props.exam?.category || null);

const reviewForm = useForm({});

const requestReview = (answer) => {
    reviewForm.post(
        route('student.exams.request-review', { attempt: props.attempt.id, answer: answer.answer_id }),
        { preserveScroll: true },
    );
};

// DİM şkalası: kəsr qiymətlər oxunaqlı yazılır (0.6667 → "2/3")
const SCALE = [[0, '0'], [1 / 3, '1/3'], [0.5, '1/2'], [2 / 3, '2/3'], [1, '1']];

const gradeLabel = (ratio) => {
    const value = Number(ratio);
    const found = SCALE.find(([step]) => Math.abs(step - value) < 0.01);

    return found ? found[1] : value.toFixed(2);
};

const getAnswerStatus = (answer) => {
    if (!answer.selected_option_id) return 'unanswered';
    if (answer.is_correct) return 'correct';
    return 'wrong';
};
</script>

<template>
    <Head title="İmtahan Nəticəsi" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">
                    İmtahan Nəticəsi
                </h2>
                <Link
                    :href="route('student.results.index')"
                    class="text-sm text-indigo-600 hover:text-indigo-800"
                >
                    Bütün Nəticələr
                </Link>
            </div>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-4xl sm:px-6 lg:px-8">
                <!-- Score Card -->
                <div class="bg-white overflow-hidden shadow-sm rounded-lg mb-6">
                    <div class="p-8 text-center">
                        <span class="px-3 py-1 bg-indigo-100 text-indigo-800 rounded-full text-sm">
                            {{ exam.subject?.name }}
                        </span>
                        <h1 class="text-2xl font-bold text-gray-900 mt-4">{{ exam.title }}</h1>

                        <div
                            v-if="attempt.awaiting_review"
                            class="mt-6 rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800"
                        >
                            Açıq suallar yoxlanılır. Aşağıdakı bal müvəqqətidir — yoxlama bitəndən
                            sonra yenilənəcək.
                        </div>

                        <!-- Ümumi bal. Yoxlama bitməyibsə "ilkin" nişanı ilə və neytral rəngdə. -->
                        <div class="mt-8">
                            <div :class="['text-5xl sm:text-6xl font-bold', getScoreColor]">
                                {{ attempt.score }}
                                <span class="text-3xl text-gray-400">/ {{ attempt.max_subject_score }}</span>
                            </div>
                            <p class="mt-2 flex flex-wrap items-center justify-center gap-2 text-gray-500">
                                <span>ümumi bal</span>
                                <span class="text-gray-400">({{ attempt.relative_score }} — 100-lük)</span>
                                <span
                                    v-if="attempt.awaiting_review"
                                    class="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-800"
                                >ilkin</span>
                            </p>
                        </div>

                        <!-- Fənn üzrə bölgü: nisbi bal (NB) burada göstərilir -->
                        <div v-if="sections.length" class="mt-8 overflow-x-auto">
                            <table class="min-w-full text-sm">
                                <thead>
                                    <tr class="text-gray-500">
                                        <th class="px-3 py-2 text-left font-medium">Fənn</th>
                                        <th class="px-3 py-2 text-right font-medium">Düzgün</th>
                                        <th class="px-3 py-2 text-right font-medium">Səhv</th>
                                        <th class="px-3 py-2 text-right font-medium">Boş</th>
                                        <th class="px-3 py-2 text-right font-medium">Nisbi bal</th>
                                        <th class="px-3 py-2 text-right font-medium">Fənn balı</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    <tr v-for="section in sections" :key="section.title">
                                        <td class="px-3 py-2 text-left font-medium text-gray-900">{{ section.title }}</td>
                                        <td class="px-3 py-2 text-right text-green-700">{{ section.correct_answers }}</td>
                                        <td class="px-3 py-2 text-right text-red-700">{{ section.wrong_answers }}</td>
                                        <td class="px-3 py-2 text-right text-gray-500">{{ section.unanswered }}</td>
                                        <td class="px-3 py-2 text-right">{{ section.relative_score }}</td>
                                        <td class="px-3 py-2 text-right font-semibold text-gray-900">
                                            {{ section.subject_score }}
                                            <span class="text-gray-400">/ {{ section.max_score }}</span>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="grid grid-cols-3 gap-4 mt-8 max-w-md mx-auto">
                            <div class="text-center">
                                <p class="text-2xl font-bold text-green-600">{{ attempt.correct_answers }}</p>
                                <p class="text-sm text-gray-500">Düzgün</p>
                            </div>
                            <div class="text-center">
                                <p class="text-2xl font-bold text-red-600">{{ attempt.wrong_answers }}</p>
                                <p class="text-sm text-gray-500">Səhv</p>
                            </div>
                            <div class="text-center">
                                <p class="text-2xl font-bold text-gray-600">
                                    {{ attempt.total_questions - attempt.correct_answers - attempt.wrong_answers }}
                                </p>
                                <p class="text-sm text-gray-500">Boş</p>
                            </div>
                        </div>

                        <!-- Zolaq nisbi balı göstərir; altında isə FAİZ yox, SAY yazılır -->
                        <div class="mt-6">
                            <div class="w-full bg-gray-200 rounded-full h-3 max-w-md mx-auto">
                                <div
                                    class="h-3 rounded-full transition-all duration-500"
                                    :class="attempt.awaiting_review ? 'bg-gray-400' : (relative >= 60 ? 'bg-green-500' : 'bg-red-500')"
                                    :style="{ width: `${Math.min(100, relative)}%` }"
                                ></div>
                            </div>
                            <p class="text-sm text-gray-500 mt-2">
                                {{ attempt.total_questions }} sualdan {{ attempt.correct_answers }}-i düzgün
                            </p>
                        </div>

                        <!-- Əvvəlki cəhdlə müqayisə -->
                        <div v-if="comparison.previous" class="mx-auto mt-8 max-w-md rounded-lg bg-gray-50 p-4 text-sm">
                            <p class="text-gray-700">
                                Əvvəlki cəhd ({{ comparison.previous.date }}):
                                <span class="font-semibold">{{ comparison.previous.relative_score }}</span> (100-lük)
                            </p>
                            <p class="mt-1">
                                Dəyişmə:
                                <span
                                    class="font-semibold"
                                    :class="comparison.change >= 0 ? 'text-green-600' : 'text-red-600'"
                                >{{ comparison.change > 0 ? '+' : '' }}{{ comparison.change }}</span>
                            </p>
                        </div>

                        <div v-if="historyPoints.length > 1" class="mt-6">
                            <LineChart :points="historyPoints" :max="100" label="Bu imtahandakı cəhdlərin nisbi balı" />
                        </div>

                        <div class="mt-6 space-y-1 text-sm text-gray-500">
                            <p v-if="contextLabel">Bölmə: {{ contextLabel }}</p>
                            <p>Tarix: {{ new Date(attempt.finished_at).toLocaleString('az-AZ') }}</p>
                        </div>
                    </div>
                </div>

                <!-- Mövzu bölgüsü -->
                <div v-if="topics.length" class="mb-6 overflow-hidden rounded-lg bg-white shadow-sm">
                    <div class="border-b border-gray-200 p-6">
                        <h3 class="text-lg font-semibold text-gray-900">Mövzu üzrə bölgü</h3>
                        <p class="mt-1 text-sm text-gray-500">Ən zəif mövzu yuxarıdadır.</p>
                    </div>
                    <ul class="divide-y divide-gray-100 p-6 pt-0">
                        <li v-for="topic in topics" :key="topic.topic" class="py-3">
                            <div class="flex justify-between text-sm">
                                <span class="font-medium text-gray-800">{{ topic.topic }}</span>
                                <span class="text-gray-500">{{ topic.correct }}/{{ topic.answered }}</span>
                            </div>
                            <ScoreBar :value="topic.accuracy" :weak="topic.accuracy < 60" class="mt-1" />
                        </li>
                    </ul>
                </div>

                <!-- Answer Details -->
                <div class="bg-white overflow-hidden shadow-sm rounded-lg">
                    <div class="p-6 border-b border-gray-200">
                        <h3 class="text-lg font-semibold text-gray-900">Sual-Cavab Analizi</h3>
                    </div>
                    <div class="divide-y divide-gray-200">
                        <div
                            v-for="(answer, index) in answers"
                            :key="answer.question_id"
                            class="p-6"
                        >
                            <div class="flex items-start gap-4">
                                <span
                                    :class="[
                                        'flex-shrink-0 w-8 h-8 rounded-full flex items-center justify-center text-sm font-medium',
                                        getAnswerStatus(answer) === 'correct'
                                            ? 'bg-green-100 text-green-800'
                                            : getAnswerStatus(answer) === 'wrong'
                                                ? 'bg-red-100 text-red-800'
                                                : 'bg-gray-100 text-gray-600'
                                    ]"
                                >
                                    {{ index + 1 }}
                                </span>
                                <div class="flex-1">
                                    <p class="text-gray-900 font-medium"><MathText :text="answer.question_text" /></p>
                                    <QuestionImage :path="answer.question_image" :alt="answer.question_image_alt" />

                                    <div v-if="answer.options?.length" class="mt-4 space-y-2">
                                        <div
                                            v-for="option in answer.options"
                                            :key="option.id"
                                            :class="[
                                                'p-3 rounded-lg text-sm',
                                                option.is_correct
                                                    ? 'bg-green-50 border border-green-200'
                                                    : option.id === answer.selected_option_id && !option.is_correct
                                                        ? 'bg-red-50 border border-red-200'
                                                        : 'bg-gray-50'
                                            ]"
                                        >
                                            <div class="flex items-center justify-between">
                                                <span>
                                                    <MathText :text="option.option_text" />
                                                    <QuestionImage
                                                        v-if="option.option_image"
                                                        :path="option.option_image"
                                                        :alt="option.option_text || 'Variant'"
                                                        size="option"
                                                        class="mt-2"
                                                    />
                                                </span>
                                                <div class="flex items-center gap-2">
                                                    <span
                                                        v-if="option.id === answer.selected_option_id"
                                                        class="text-xs px-2 py-0.5 rounded bg-indigo-100 text-indigo-800"
                                                    >
                                                        Seçiminiz
                                                    </span>
                                                    <span
                                                        v-if="option.is_correct"
                                                        class="text-xs px-2 py-0.5 rounded bg-green-100 text-green-800"
                                                    >
                                                        Düzgün cavab
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Açıq suallar: cavab, düzgün cavab/meyar, qiymət və əsaslandırma -->
                                    <div v-if="answer.type !== 'multiple_choice'" class="mt-4 space-y-3 text-sm">
                                        <div>
                                            <p class="font-medium text-gray-700">Sənin cavabın</p>
                                            <p v-if="answer.open_answer" class="mt-1 whitespace-pre-line rounded-lg bg-gray-50 p-3 text-gray-900">{{ answer.open_answer }}</p>
                                            <p v-else class="mt-1 italic text-gray-500">Bu suala cavab verilməyib</p>
                                        </div>

                                        <div v-if="answer.accepted_answers?.length">
                                            <p class="font-medium text-gray-700">Qəbul olunan cavablar</p>
                                            <p class="mt-1 text-green-700">{{ answer.accepted_answers.join(', ') }}</p>
                                        </div>

                                        <div v-if="answer.grading_rubric">
                                            <p class="font-medium text-gray-700">Düzgün cavab və meyar</p>
                                            <p class="mt-1 whitespace-pre-line text-gray-700">{{ answer.grading_rubric }}</p>
                                        </div>

                                        <div v-if="answer.awaiting_review" class="rounded-lg bg-amber-50 p-3 text-amber-800">
                                            Bu cavab yoxlanılır — qiymət sonra əlavə olunacaq.
                                        </div>

                                        <div v-else-if="answer.grade_ratio !== null && answer.grade_ratio !== undefined" class="rounded-lg bg-gray-50 p-3">
                                            <p class="font-medium text-gray-800">
                                                Qiymət: {{ gradeLabel(answer.grade_ratio) }}
                                                <span class="text-gray-500">({{ answer.score_earned }} bal)</span>
                                            </p>
                                            <p v-if="answer.grade_comment" class="mt-1 text-gray-700">{{ answer.grade_comment }}</p>

                                            <template v-if="answer.grade_source === 'ai'">
                                                <p class="mt-2 text-xs text-gray-500">
                                                    İlkin qiymət avtomatik verilib. Razı deyilsənsə, müəllim
                                                    yenidən baxa bilər.
                                                </p>
                                                <p v-if="answer.review_requested" class="mt-1 text-xs font-medium text-indigo-700">
                                                    Yenidən baxış istənilib.
                                                </p>
                                                <button
                                                    v-else
                                                    type="button"
                                                    class="mt-2 rounded-lg border border-indigo-300 px-3 py-1.5 text-xs font-medium text-indigo-700 hover:bg-indigo-50 disabled:opacity-50"
                                                    :disabled="reviewForm.processing"
                                                    @click="requestReview(answer)"
                                                >Yenidən baxılsın</button>
                                            </template>
                                        </div>
                                    </div>

                                    <p
                                        v-else-if="!answer.selected_option_id"
                                        class="mt-2 text-sm text-gray-500 italic"
                                    >
                                        Bu suala cavab verilməyib
                                    </p>

                                    <p v-if="answer.explanation" class="mt-3 rounded-lg bg-blue-50 p-3 text-sm text-blue-900">
                                        <span class="font-medium">İzah:</span> {{ answer.explanation }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Actions -->
                <div class="mt-6 flex justify-center gap-4">
                    <Link
                        :href="examUrl"
                        class="px-6 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700"
                    >
                        Yenidən İmtahan Ver
                    </Link>
                    <Link
                        :href="route('student.exams.index')"
                        class="px-6 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200"
                    >
                        Mənim imtahanlarım
                    </Link>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

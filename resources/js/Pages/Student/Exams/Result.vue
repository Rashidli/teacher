<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import MathText from '@/Components/MathText.vue';
import { computed } from 'vue';

const props = defineProps({
    attempt: Object,
    sections: { type: Array, default: () => [] },
    exam: Object,
    answers: Array,
});

const percentage = computed(() => {
    if (!props.attempt.total_questions) return 0;
    return Math.round((props.attempt.correct_answers / props.attempt.total_questions) * 100);
});

const getScoreColor = computed(() => {
    if (percentage.value >= 80) return 'text-green-600';
    if (percentage.value >= 60) return 'text-yellow-600';
    return 'text-red-600';
});

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

                        <!-- Ümumi bal: maksimumla və faizlə. "NB" yalnız fənn səviyyəsindədir. -->
                        <div class="mt-8">
                            <div :class="['text-5xl sm:text-6xl font-bold', getScoreColor]">
                                {{ attempt.score }}
                                <span class="text-3xl text-gray-400">/ {{ attempt.max_subject_score }}</span>
                            </div>
                            <p class="text-gray-500 mt-2">
                                ümumi bal
                                <span class="text-gray-400">({{ attempt.relative_score }}%)</span>
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

                        <div class="mt-6">
                            <div class="w-full bg-gray-200 rounded-full h-3 max-w-md mx-auto">
                                <div
                                    class="h-3 rounded-full transition-all duration-500"
                                    :class="percentage >= 60 ? 'bg-green-500' : 'bg-red-500'"
                                    :style="{ width: `${percentage}%` }"
                                ></div>
                            </div>
                            <p class="text-sm text-gray-500 mt-2">{{ percentage }}% düzgün</p>
                        </div>

                        <div class="mt-6 text-sm text-gray-500">
                            <p>Qrup: {{ attempt.group?.name }}</p>
                            <p>Tarix: {{ new Date(attempt.finished_at).toLocaleString('az-AZ') }}</p>
                        </div>
                    </div>
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
                                                <span><MathText :text="option.option_text" /></span>
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

                                    <p
                                        v-if="!answer.selected_option_id"
                                        class="mt-2 text-sm text-gray-500 italic"
                                    >
                                        Bu suala cavab verilməyib
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Actions -->
                <div class="mt-6 flex justify-center gap-4">
                    <Link
                        :href="route('student.exams.show', exam.id)"
                        class="px-6 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700"
                    >
                        Yenidən İmtahan Ver
                    </Link>
                    <Link
                        :href="route('student.exams.index')"
                        class="px-6 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200"
                    >
                        Digər İmtahanlar
                    </Link>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

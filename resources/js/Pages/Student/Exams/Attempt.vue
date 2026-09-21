<script setup>
import { Head, router } from '@inertiajs/vue3';
import MathText from '@/Components/MathText.vue';
import { ref, computed, onMounted, onUnmounted } from 'vue';

const props = defineProps({
    attempt: Object,
    exam: Object,
    questions: Array,
    answers: Object,
});

const selectedAnswers = ref({ ...(props.answers || {}) });

// Açıq cavablar (open_coded / open_written) — serverdən gələn mətnlərlə doldurulur
const openAnswers = ref(
    Object.fromEntries(
        (props.questions || [])
            .filter((question) => question.type !== 'multiple_choice')
            .map((question) => [question.id, question.open_answer ?? ''])
    )
);

let openAnswerTimer = null;
const timeRemaining = ref(props.attempt?.remaining_time || 0);
const isSaving = ref(false);
const isFinishing = ref(false);
const showMobileNav = ref(false);

const formattedTime = computed(() => {
    const minutes = Math.floor(timeRemaining.value / 60);
    const seconds = timeRemaining.value % 60;
    return `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
});

const isTimeWarning = computed(() => timeRemaining.value <= 300);
const isTimeCritical = computed(() => timeRemaining.value <= 60);

const answeredCount = computed(() => {
    const chosen = Object.keys(selectedAnswers.value).filter((key) => selectedAnswers.value[key]).length;
    const written = Object.values(openAnswers.value).filter((text) => String(text).trim() !== '').length;

    return chosen + written;
});

let timerInterval = null;

onMounted(() => {
    // Əgər vaxt artıq bitmişsə, dərhal submit et
    if (timeRemaining.value <= 0) {
        finishExam(true);
        return;
    }

    timerInterval = setInterval(() => {
        if (timeRemaining.value > 0) {
            timeRemaining.value--;
        } else {
            // Vaxt bitdi - avtomatik submit (confirm olmadan)
            clearInterval(timerInterval);
            finishExam(true);
        }
    }, 1000);
});

onUnmounted(() => {
    if (timerInterval) {
        clearInterval(timerInterval);
    }

    clearTimeout(openAnswerTimer);
});

const saveOpenAnswer = (questionId) => {
    clearTimeout(openAnswerTimer);

    openAnswerTimer = setTimeout(async () => {
        isSaving.value = true;

        try {
            await axios.post(route('student.exams.save-answer', props.attempt.id), {
                question_id: questionId,
                open_answer: openAnswers.value[questionId] ?? '',
            });
        } catch (error) {
            console.error('Error saving answer:', error);
        } finally {
            isSaving.value = false;
        }
    }, 800);
};

const selectAnswer = async (questionId, optionId) => {
    selectedAnswers.value[questionId] = optionId;
    isSaving.value = true;

    try {
        await axios.post(route('student.exams.save-answer', props.attempt.id), {
            question_id: questionId,
            selected_option_id: optionId,
        });
    } catch (error) {
        console.error('Error saving answer:', error);
    } finally {
        isSaving.value = false;
    }
};

const scrollToQuestion = (index) => {
    showMobileNav.value = false;
    const element = document.getElementById(`question-${index}`);
    if (element) {
        element.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
};

const finishExam = (autoFinish = false) => {
    if (isFinishing.value) return;

    const unanswered = props.questions.length - answeredCount.value;

    // Əgər avtomatik bitirmə deyilsə və cavablanmamış sual varsa, confirm göstər
    if (!autoFinish && unanswered > 0 && timeRemaining.value > 0) {
        if (!confirm(`${unanswered} cavablanmamış sual var. Bitirmək istəyirsiniz?`)) {
            return;
        }
    }

    // Vaxt bitdikdə xəbərdarlıq göstər
    if (autoFinish) {
        alert('Vaxt bitdi! İmtahanınız avtomatik göndərilir.');
    }

    isFinishing.value = true;
    router.post(route('student.exams.finish', props.attempt.id));
};

const getQuestionStatus = (question) => {
    if (selectedAnswers.value[question.id]) {
        return 'answered';
    }
    return 'unanswered';
};
</script>

<template>
    <Head :title="`İmtahan: ${exam?.title}`" />

    <div class="min-h-screen bg-gray-100">
        <!-- Fixed Timer Header -->
        <div class="fixed top-0 left-0 right-0 bg-white shadow-sm z-50">
            <div class="max-w-7xl mx-auto px-3 sm:px-4 py-2 sm:py-3">
                <div class="flex items-center justify-between gap-2">
                    <!-- Title - hidden on very small screens -->
                    <div class="hidden sm:block min-w-0 flex-shrink">
                        <h1 class="font-semibold text-gray-900 truncate">{{ exam?.title }}</h1>
                        <p class="text-sm text-gray-500 truncate">{{ exam?.subject?.name }}</p>
                    </div>

                    <!-- Mobile: Question nav toggle -->
                    <button
                        @click="showMobileNav = !showMobileNav"
                        class="lg:hidden flex items-center gap-2 px-3 py-2 bg-gray-100 rounded-lg text-sm font-medium"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                        </svg>
                        <span>{{ answeredCount }}/{{ questions.length }}</span>
                    </button>

                    <!-- Stats & Timer -->
                    <div class="flex items-center gap-2 sm:gap-4">
                        <!-- Answered count - hidden on mobile (shown in toggle button) -->
                        <div class="hidden lg:block text-center">
                            <p class="text-xs sm:text-sm text-gray-500">Cavablandı</p>
                            <p class="font-semibold text-sm sm:text-base">{{ answeredCount }}/{{ questions.length }}</p>
                        </div>

                        <!-- Timer -->
                        <div
                            :class="[
                                'px-2 sm:px-4 py-1 sm:py-2 rounded-lg font-mono text-base sm:text-xl font-bold',
                                isTimeCritical ? 'bg-red-100 text-red-700 animate-pulse' :
                                isTimeWarning ? 'bg-yellow-100 text-yellow-700' : 'bg-gray-100 text-gray-700'
                            ]"
                        >
                            {{ formattedTime }}
                        </div>

                        <!-- Finish button -->
                        <button
                            @click="finishExam(false)"
                            :disabled="isFinishing"
                            class="px-3 sm:px-4 py-1.5 sm:py-2 bg-red-600 text-white text-sm sm:text-base rounded-lg hover:bg-red-700 disabled:opacity-50"
                        >
                            {{ isFinishing ? '...' : 'Bitir' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Mobile Question Navigation Drawer -->
        <div
            v-if="showMobileNav"
            class="fixed inset-0 z-50 lg:hidden"
        >
            <!-- Backdrop -->
            <div
                class="absolute inset-0 bg-black/50"
                @click="showMobileNav = false"
            ></div>

            <!-- Drawer -->
            <div class="absolute top-0 left-0 bottom-0 w-72 bg-white shadow-xl overflow-y-auto">
                <div class="p-4">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="font-semibold text-gray-900">Suallar</h3>
                        <button
                            @click="showMobileNav = false"
                            class="p-2 hover:bg-gray-100 rounded-lg"
                        >
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>

                    <div class="grid grid-cols-5 gap-2">
                        <button
                            v-for="(question, index) in questions"
                            :key="question.id"
                            @click="scrollToQuestion(index)"
                            :class="[
                                'w-10 h-10 rounded text-sm font-medium transition',
                                getQuestionStatus(question) === 'answered'
                                    ? 'bg-green-100 text-green-800'
                                    : 'bg-gray-100 text-gray-600'
                            ]"
                        >
                            {{ index + 1 }}
                        </button>
                    </div>

                    <div class="mt-4 pt-4 border-t border-gray-200">
                        <div class="flex items-center gap-2 text-sm text-gray-500">
                            <span class="w-3 h-3 bg-green-100 rounded"></span>
                            <span>Cavablandı ({{ answeredCount }})</span>
                        </div>
                        <div class="flex items-center gap-2 text-sm text-gray-500 mt-1">
                            <span class="w-3 h-3 bg-gray-100 rounded"></span>
                            <span>Cavablanmayıb ({{ questions.length - answeredCount }})</span>
                        </div>
                    </div>

                    <!-- Saving indicator -->
                    <div v-if="isSaving" class="mt-4 text-sm text-indigo-600 flex items-center gap-2">
                        <svg class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <span>Yadda saxlanılır...</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="pt-14 sm:pt-20 pb-8">
            <div class="max-w-7xl mx-auto px-3 sm:px-4 lg:px-8">
                <div class="flex gap-6">
                    <!-- Desktop Question Navigation Sidebar -->
                    <div class="hidden lg:block w-64 flex-shrink-0">
                        <div class="bg-white rounded-lg shadow-sm p-4 sticky top-24">
                            <h3 class="font-semibold text-gray-900 mb-4">Suallar</h3>
                            <div class="grid grid-cols-5 gap-2">
                                <button
                                    v-for="(question, index) in questions"
                                    :key="question.id"
                                    @click="scrollToQuestion(index)"
                                    :class="[
                                        'w-8 h-8 rounded text-sm font-medium transition',
                                        getQuestionStatus(question) === 'answered'
                                            ? 'bg-green-100 text-green-800 hover:bg-green-200'
                                            : 'bg-gray-100 text-gray-600 hover:bg-gray-200'
                                    ]"
                                >
                                    {{ index + 1 }}
                                </button>
                            </div>
                            <div class="mt-4 pt-4 border-t border-gray-200">
                                <div class="flex items-center gap-2 text-sm text-gray-500">
                                    <span class="w-3 h-3 bg-green-100 rounded"></span>
                                    <span>Cavablandı</span>
                                </div>
                                <div class="flex items-center gap-2 text-sm text-gray-500 mt-1">
                                    <span class="w-3 h-3 bg-gray-100 rounded"></span>
                                    <span>Cavablanmayıb</span>
                                </div>
                            </div>

                            <!-- Saving indicator -->
                            <div v-if="isSaving" class="mt-4 text-sm text-indigo-600 flex items-center gap-2">
                                <svg class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                <span>Yadda saxlanılır...</span>
                            </div>
                        </div>
                    </div>

                    <!-- All Questions -->
                    <div class="flex-1 min-w-0 space-y-4 sm:space-y-6">
                        <div
                            v-for="(question, index) in questions"
                            :key="question.id"
                            :id="`question-${index}`"
                            class="bg-white rounded-lg shadow-sm p-4 sm:p-6 scroll-mt-20"
                        >
                            <!-- Question Header -->
                            <div class="flex items-center gap-2 sm:gap-3 mb-3 sm:mb-4">
                                <span
                                    :class="[
                                        'flex-shrink-0 w-8 h-8 sm:w-10 sm:h-10 rounded-full flex items-center justify-center text-xs sm:text-sm font-bold',
                                        getQuestionStatus(question) === 'answered'
                                            ? 'bg-green-100 text-green-800'
                                            : 'bg-indigo-100 text-indigo-800'
                                    ]"
                                >
                                    {{ index + 1 }}
                                </span>
                                <span
                                    v-if="getQuestionStatus(question) === 'answered'"
                                    class="text-xs px-2 py-1 bg-green-100 text-green-700 rounded-full"
                                >
                                    Cavablandı
                                </span>
                            </div>

                            <!-- Question Text -->
                            <div class="mb-4 sm:mb-6">
                                <p class="text-base sm:text-lg text-gray-900"><MathText :text="question.question_text" /></p>
                                <img
                                    v-if="question.question_image"
                                    :src="`/storage/${question.question_image}`"
                                    alt="Sual şəkli"
                                    class="mt-4 max-w-full sm:max-w-lg rounded-lg border border-gray-200"
                                />
                            </div>

                            <!-- Options -->
                            <div v-if="question.type === 'multiple_choice'" class="space-y-2 sm:space-y-3">
                                <button
                                    v-for="option in question.options"
                                    :key="option.id"
                                    @click="selectAnswer(question.id, option.id)"
                                    :class="[
                                        'w-full flex items-center gap-3 sm:gap-4 p-3 sm:p-4 rounded-lg border-2 text-left transition',
                                        selectedAnswers[question.id] === option.id
                                            ? 'border-indigo-500 bg-indigo-50'
                                            : 'border-gray-200 hover:border-gray-300 hover:bg-gray-50'
                                    ]"
                                >
                                    <span
                                        :class="[
                                            'flex-shrink-0 w-7 h-7 sm:w-8 sm:h-8 rounded-full flex items-center justify-center text-xs sm:text-sm font-medium',
                                            selectedAnswers[question.id] === option.id
                                                ? 'bg-indigo-600 text-white'
                                                : 'bg-gray-100 text-gray-600'
                                        ]"
                                    >
                                        {{ option.option_letter }}
                                    </span>
                                    <span class="flex-1 text-sm sm:text-base"><MathText :text="option.option_text" /></span>
                                </button>
                            </div>

                            <!-- Açıq cavab: qısa cavab (open_coded) və ya yazılı həll (open_written) -->
                            <div v-else class="mt-4">
                                <textarea
                                    v-model="openAnswers[question.id]"
                                    @input="saveOpenAnswer(question.id)"
                                    class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm sm:text-base"
                                    :rows="question.type === 'open_coded' ? 2 : 6"
                                    :placeholder="question.type === 'open_coded'
                                        ? 'Cavabı yazın (məs: 0,5)'
                                        : 'Həlli addım-addım yazın...'"
                                ></textarea>
                                <p class="mt-1 text-xs text-gray-500">
                                    {{ question.type === 'open_coded'
                                        ? 'Rəqəm cavabı: 0,5 / 0.5 / 1/2 — hamısı qəbul olunur.'
                                        : 'Bu sual müəllim tərəfindən yoxlanılacaq.' }}
                                </p>
                            </div>
                        </div>

                        <!-- Finish Button at Bottom -->
                        <div class="bg-white rounded-lg shadow-sm p-4 sm:p-6 text-center">
                            <p class="text-gray-600 mb-3 sm:mb-4 text-sm sm:text-base">
                                {{ answeredCount }} / {{ questions.length }} sual cavablandı
                            </p>
                            <button
                                @click="finishExam(false)"
                                :disabled="isFinishing"
                                class="w-full sm:w-auto px-6 sm:px-8 py-2.5 sm:py-3 bg-green-600 text-white rounded-lg hover:bg-green-700 disabled:opacity-50 font-semibold text-sm sm:text-base"
                            >
                                {{ isFinishing ? 'Bitirilir...' : 'İmtahanı Bitir' }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

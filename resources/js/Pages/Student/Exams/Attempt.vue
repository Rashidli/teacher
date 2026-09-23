<script setup>
import { Head, router } from '@inertiajs/vue3';
import MathText from '@/Components/MathText.vue';
import QuestionImage from '@/Components/QuestionImage.vue';
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

/*
 * DİM-in KODLAŞDIRILAN açıq tapşırıqları.
 *
 * Cavab serverə HƏMİŞƏ mətn kodu kimi gedir (`open_answer`) — interfeys fərqlidir, saxlama
 * eynidir (bax `App\Support\CodedAnswer`):
 *   seçim      → "A,C"      (hərflər, sıra əhəmiyyətsiz)
 *   ardıcıllıq → "C,A,B"    (hərflər, ŞAGİRDİN sırası ilə)
 *   uyğunluq   → "1-2,2-1"  (sol bəndin nömrəsi - sağ bəndin nömrəsi)
 */
const codedSubtype = (question) => (question.type === 'open_coded'
    ? (question.subtype || 'numeric')
    : null);

/**
 * Sabit qarışdırma: eyni cəhddə eyni sual həmişə eyni sıra ilə görünür (səhifə
 * yeniləndikdə bəndlər yerini dəyişməməlidir), amma düzgün sıra gizli qalır.
 */
const shuffled = (items, seed) => {
    const rows = items.map((item, index) => ({ item, index }));
    let state = seed || 1;

    // Kiçik determinik PRNG (mulberry32): kitabxana lazım deyil
    const random = () => {
        state |= 0;
        state = (state + 0x6D2B79F5) | 0;
        let t = Math.imul(state ^ (state >>> 15), 1 | state);
        t = (t + Math.imul(t ^ (t >>> 7), 61 | t)) ^ t;

        return ((t ^ (t >>> 14)) >>> 0) / 4294967296;
    };

    for (let i = rows.length - 1; i > 0; i -= 1) {
        const j = Math.floor(random() * (i + 1));
        [rows[i], rows[j]] = [rows[j], rows[i]];
    }

    return rows;
};

const seedFor = (question) => (props.attempt?.id ?? 1) * 1000 + question.id;

/** Seçim və ardıcıllıq: variantlar qarışıq göstərilir */
const displayOptions = (question) => shuffled(question.options ?? [], seedFor(question))
    .map((row) => row.item);

/** Uyğunluq: sağ sütun qarışdırılır, dəyər isə KANONİK nömrədir */
const rightChoices = (question) => shuffled(question.pairs?.right ?? [], seedFor(question))
    .map((row) => ({ value: row.index + 1, text: row.item }));

/* ------------------------------ kodlaşdırılan cavabların yerli vəziyyəti ---- */

const parseCode = (value) => String(value ?? '')
    .split(',')
    .map((part) => part.trim().toUpperCase())
    .filter((part) => part !== '');

/** Seçim: hansı hərflər işarələnib */
const selectedLetters = ref({});

/** Ardıcıllıq: şagirdin düzdüyü hərf sırası */
const orderedLetters = ref({});

/** Uyğunluq: sol bəndin nömrəsi → seçilmiş sağ bəndin nömrəsi */
const matchedPairs = ref({});

(props.questions || []).forEach((question) => {
    const subtype = codedSubtype(question);
    const code = parseCode(question.open_answer);

    if (subtype === 'multi_select') {
        selectedLetters.value[question.id] = code;
    } else if (subtype === 'ordering') {
        // Yarımçıq və ya köhnəlmiş cavab: göstərilən bəndlərlə tamamlanır
        const letters = displayOptions(question).map((option) => option.option_letter);
        const kept = code.filter((letter) => letters.includes(letter));

        orderedLetters.value[question.id] = [
            ...kept,
            ...letters.filter((letter) => !kept.includes(letter)),
        ];
    } else if (subtype === 'matching') {
        const chosen = {};

        code.forEach((pair) => {
            const [left, right] = pair.split('-');

            if (left && right) {
                chosen[Number(left)] = Number(right);
            }
        });

        matchedPairs.value[question.id] = chosen;
    }
});

const saveCode = (questionId, code) => {
    openAnswers.value[questionId] = code;
    saveOpenAnswer(questionId, 0);
};

const toggleLetter = (question, letter) => {
    const current = selectedLetters.value[question.id] ?? [];

    selectedLetters.value[question.id] = current.includes(letter)
        ? current.filter((value) => value !== letter)
        : [...current, letter];

    // Kod əlifba sırası ilə yazılır: yoxlama sırasından asılı deyil
    saveCode(question.id, [...selectedLetters.value[question.id]].sort().join(','));
};

const moveLetter = (question, index, delta) => {
    const letters = [...(orderedLetters.value[question.id] ?? [])];
    const target = index + delta;

    if (target < 0 || target >= letters.length) {
        return;
    }

    [letters[index], letters[target]] = [letters[target], letters[index]];
    orderedLetters.value[question.id] = letters;

    saveCode(question.id, letters.join(','));
};

const setMatch = (question, leftIndex, rightValue) => {
    const chosen = { ...(matchedPairs.value[question.id] ?? {}) };

    if (rightValue) {
        chosen[leftIndex] = Number(rightValue);
    } else {
        delete chosen[leftIndex];
    }

    matchedPairs.value[question.id] = chosen;

    saveCode(question.id, Object.keys(chosen)
        .map(Number)
        .sort((a, b) => a - b)
        .map((left) => `${left}-${chosen[left]}`)
        .join(','));
};

/** Bəndin mətni hərfə görə (ardıcıllıq siyahısında göstərmək üçün) */
const optionByLetter = (question, letter) => (question.options ?? [])
    .find((option) => option.option_letter === letter);

// Fənn bölmələri: çoxfənli imtahanda tablarla keçid
const sections = computed(() => {
    const seen = new Map();

    (props.questions || []).forEach((question) => {
        if (question.section_id && !seen.has(question.section_id)) {
            seen.set(question.section_id, { id: question.section_id, title: question.section_title });
        }
    });

    return [...seen.values()];
});

const activeSection = ref(null);

const visibleQuestions = computed(() => (activeSection.value
    ? (props.questions || []).filter((question) => question.section_id === activeSection.value)
    : (props.questions || [])));
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

/*
 * Yazarkən hər hərfdə sorğu getməsin. Kodlaşdırılan tapşırıqlarda isə klik dərhal
 * yadda saxlanılır (gecikmə 0) — orada "yazma" yoxdur.
 */
const saveOpenAnswer = (questionId, delay = 800) => {
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
    }, delay);
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
                        <!-- Fənn tabları (yalnız çoxfənli imtahanda) -->
                        <div v-if="sections.length > 1" class="bg-white rounded-lg shadow-sm p-2 flex flex-wrap gap-2">
                            <button
                                type="button"
                                @click="activeSection = null"
                                :class="['px-3 py-1.5 text-sm rounded transition',
                                    activeSection === null ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-700']"
                            >
                                Hamısı
                            </button>
                            <button
                                v-for="section in sections"
                                :key="section.id"
                                type="button"
                                @click="activeSection = section.id"
                                :class="['px-3 py-1.5 text-sm rounded transition',
                                    activeSection === section.id ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-700']"
                            >
                                {{ section.title }}
                            </button>
                        </div>

                        <div
                            v-for="(question, index) in visibleQuestions"
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

                            <!-- Mətn/mənbə: eyni mətn bir neçə sualda görünə bilər -->
                            <details v-if="question.passage" class="mb-4 rounded-lg border border-gray-200 bg-gray-50 p-3" open>
                                <summary class="cursor-pointer text-sm font-medium text-gray-700">
                                    {{ question.passage.title }}
                                </summary>
                                <div class="mt-2 whitespace-pre-line text-sm text-gray-800">{{ question.passage.body }}</div>
                                <p v-if="question.passage.source" class="mt-2 text-xs text-gray-500">
                                    Mənbə: {{ question.passage.source }}
                                </p>
                            </details>

                            <!-- Question Text -->
                            <div class="mb-4 sm:mb-6">
                                <p class="text-base sm:text-lg text-gray-900"><MathText :text="question.question_text" /></p>
                                <QuestionImage :url="question.question_image_url" :alt="question.question_image_alt" />
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
                                    <span class="flex-1 text-sm sm:text-base">
                                        <MathText :text="option.option_text" />
                                        <QuestionImage
                                            v-if="option.option_image_url"
                                            :url="option.option_image_url"
                                            :alt="option.option_text || `Variant ${option.option_letter}`"
                                            size="option"
                                            class="mt-2"
                                        />
                                    </span>
                                </button>
                            </div>

                            <!--
                                SEÇİM (multi_select): bir neçə düzgün variant.
                                Checkbox-lar: seçim sayı göstərilmir — DİM-də də göstərilmir.
                            -->
                            <div v-else-if="codedSubtype(question) === 'multi_select'" class="space-y-2">
                                <label
                                    v-for="option in displayOptions(question)"
                                    :key="option.id"
                                    :class="[
                                        'flex items-center gap-3 p-3 rounded-lg border-2 cursor-pointer transition',
                                        (selectedLetters[question.id] || []).includes(option.option_letter)
                                            ? 'border-indigo-500 bg-indigo-50'
                                            : 'border-gray-200 hover:border-gray-300',
                                    ]"
                                >
                                    <input
                                        type="checkbox"
                                        class="w-5 h-5 rounded text-indigo-600 border-gray-300 focus:ring-indigo-500"
                                        :checked="(selectedLetters[question.id] || []).includes(option.option_letter)"
                                        @change="toggleLetter(question, option.option_letter)"
                                    />
                                    <span class="flex-1 text-sm sm:text-base">
                                        <MathText :text="option.option_text" />
                                    </span>
                                </label>
                                <p class="text-xs text-gray-500">Bir neçə variant seçilə bilər.</p>
                            </div>

                            <!--
                                ARDICILLIQ (ordering): bəndlər qarışıq gəlir, şagird onları
                                yuxarı/aşağı sürüşdürərək düzür. Düymələr klaviatura ilə də
                                işlədiyi üçün sürükləməyə alternativ deyil, əsas üsuldur.
                            -->
                            <div v-else-if="codedSubtype(question) === 'ordering'" class="space-y-2">
                                <div
                                    v-for="(letter, index) in (orderedLetters[question.id] || [])"
                                    :key="letter"
                                    class="flex items-center gap-3 p-3 rounded-lg border-2 border-gray-200"
                                >
                                    <span class="flex-shrink-0 w-7 h-7 rounded-full bg-indigo-100 text-indigo-800 flex items-center justify-center text-sm font-bold">
                                        {{ index + 1 }}
                                    </span>
                                    <span class="flex-1 text-sm sm:text-base">
                                        <MathText :text="optionByLetter(question, letter)?.option_text ?? ''" />
                                    </span>
                                    <button
                                        type="button"
                                        class="w-9 h-9 rounded border border-gray-300 text-gray-600 disabled:opacity-30"
                                        :disabled="index === 0"
                                        aria-label="Yuxarı"
                                        @click="moveLetter(question, index, -1)"
                                    >↑</button>
                                    <button
                                        type="button"
                                        class="w-9 h-9 rounded border border-gray-300 text-gray-600 disabled:opacity-30"
                                        :disabled="index === (orderedLetters[question.id] || []).length - 1"
                                        aria-label="Aşağı"
                                        @click="moveLetter(question, index, 1)"
                                    >↓</button>
                                </div>
                                <p class="text-xs text-gray-500">Bəndləri düzgün ardıcıllıqla düzün.</p>
                            </div>

                            <!--
                                UYĞUNLUQ (matching): sol sütunun hər bəndinə sağdan biri seçilir.
                                Sağ sütun qarışıqdır, seçim isə kanonik nömrə ilə saxlanılır.
                            -->
                            <div v-else-if="codedSubtype(question) === 'matching'" class="space-y-2">
                                <div
                                    v-for="(left, index) in (question.pairs?.left || [])"
                                    :key="index"
                                    class="flex flex-wrap items-center gap-3 p-3 rounded-lg border-2 border-gray-200"
                                >
                                    <span class="flex-shrink-0 w-7 h-7 rounded-full bg-gray-100 text-gray-700 flex items-center justify-center text-sm font-bold">
                                        {{ index + 1 }}
                                    </span>
                                    <span class="flex-1 min-w-[8rem] text-sm sm:text-base">
                                        <MathText :text="left" />
                                    </span>
                                    <select
                                        class="rounded-md border-gray-300 text-sm"
                                        :value="(matchedPairs[question.id] || {})[index + 1] ?? ''"
                                        :aria-label="`${left} üçün uyğun bənd`"
                                        @change="setMatch(question, index + 1, $event.target.value)"
                                    >
                                        <option value="">— seç —</option>
                                        <option
                                            v-for="choice in rightChoices(question)"
                                            :key="choice.value"
                                            :value="choice.value"
                                        >{{ choice.text }}</option>
                                    </select>
                                </div>
                                <p class="text-xs text-gray-500">Hər bəndə uyğun gələni seçin.</p>
                            </div>

                            <!-- Açıq cavab: hesablama (open_coded) və ya yazılı həll (open_written) -->
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

<script setup>
/*
 * Sual forması — admin və müəllim tərəfi eyni komponenti işlədir.
 * Səhifə `useForm` nümunəsini prop kimi ötürür və `submit` hadisəsini dinləyir.
 *
 * Variant sayı sualda deyil, imtahanda təyin olunur (exam.options_per_question),
 * ona görə variant əlavə etmə/silmə düymələri yoxdur.
 */
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import MathText from '@/Components/MathText.vue';
import { Link } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    form: { type: Object, required: true },
    exam: { type: Object, required: true },
    topics: { type: Array, default: () => [] },
    // Sual neçə cəhddə işlənib: redaktə köhnə nəticələrə təsir edə bilər
    attemptUsage: { type: Number, default: 0 },
    submitLabel: { type: String, default: 'Yadda saxla' },
    cancelHref: { type: String, required: true },
    // Redaktə zamanı mövcud şəklin ünvanı
    existingImageUrl: { type: String, default: null },
});

const emit = defineEmits(['submit']);

const LETTERS = ['A', 'B', 'C', 'D', 'E'];

const optionCount = computed(() => props.exam.options_per_question ?? 5);
const isMultipleChoice = computed(() => props.form.type === 'multiple_choice');
const isOpenCoded = computed(() => props.form.type === 'open_coded');

const imagePreview = ref(null);
const showMathHelp = ref(false);
const previewMode = ref(false);

const mathShortcuts = [
    { label: 'Kəsr', formula: '\\frac{a}{b}' },
    { label: 'Kvadrat kök', formula: '\\sqrt{x}' },
    { label: 'Kvadrat', formula: 'x^2' },
    { label: 'Alt indeks', formula: 'x_n' },
    { label: 'Cəm (Σ)', formula: '\\sum_{i=1}^{n}' },
    { label: 'İnteqral', formula: '\\int_{a}^{b}' },
    { label: 'Pi (π)', formula: '\\pi' },
    { label: 'Sonsuzluq (∞)', formula: '\\infty' },
    { label: 'Ox (→)', formula: '\\rightarrow' },
    { label: 'Kimyəvi reaksiya', formula: '\\ce{H2O}' },
    { label: 'CO₂', formula: '\\ce{CO2}' },
    { label: 'H₂SO₄', formula: '\\ce{H2SO4}' },
];

// Variantlar həmişə imtahandakı sayda olmalıdır (tip dəyişəndə də)
const syncOptions = () => {
    if (!isMultipleChoice.value) {
        return;
    }

    const options = props.form.options ?? [];

    while (options.length < optionCount.value) {
        options.push({
            option_letter: LETTERS[options.length],
            option_text: '',
            option_image: null,
            is_correct: false,
        });
    }

    options.length = optionCount.value;
    options.forEach((option, index) => { option.option_letter = LETTERS[index]; });

    if (!options.some((option) => option.is_correct)) {
        options[0].is_correct = true;
    }

    props.form.options = options;
};

watch(() => props.form.type, syncOptions, { immediate: true });

const setCorrectOption = (index) => {
    props.form.options.forEach((option, i) => { option.is_correct = i === index; });
};

const handleImageChange = (event) => {
    const file = event.target.files[0];

    if (!file) {
        return;
    }

    props.form.question_image = file;
    props.form.remove_image = false;

    const reader = new FileReader();
    reader.onload = (e) => { imagePreview.value = e.target.result; };
    reader.readAsDataURL(file);
};

const removeImage = () => {
    props.form.question_image = null;
    props.form.remove_image = true;
    imagePreview.value = null;
};

const handleOptionImageChange = (event, index) => {
    props.form.options[index].option_image = event.target.files[0] ?? null;
};

const insertFormula = (formula, optionIndex = null) => {
    if (optionIndex !== null) {
        props.form.options[optionIndex].option_text += ` $${formula}$ `;
    } else {
        props.form.question_text += ` $${formula}$ `;
    }
};

const addAcceptedAnswer = () => {
    props.form.accepted_answers.push('');
};

const removeAcceptedAnswer = (index) => {
    if (props.form.accepted_answers.length > 1) {
        props.form.accepted_answers.splice(index, 1);
    }
};

// Mövcud şəkil silinməyibsə göstərilir
const visibleExistingImage = computed(
    () => !imagePreview.value && !props.form.remove_image && props.existingImageUrl
);
</script>

<template>
    <div class="space-y-4">
        <!-- Formula yardımı -->
        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
            <button
                type="button"
                @click="showMathHelp = !showMathHelp"
                class="flex items-center gap-2 text-sm font-medium text-blue-700 w-full"
            >
                Formula daxil etmə qaydası
                <svg :class="['w-4 h-4 ml-auto transition', showMathHelp ? 'rotate-180' : '']" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                </svg>
            </button>
            <div v-if="showMathHelp" class="mt-3 space-y-2 text-xs text-blue-800">
                <p>Formula yazmaq üçün <code class="bg-blue-100 px-1 rounded">$...$</code> işarələri arasına yazın:</p>
                <div class="grid grid-cols-2 gap-2 mt-2">
                    <div class="bg-white rounded p-2">
                        <p class="font-medium">Riyaziyyat nümunələri:</p>
                        <p><code>$\frac{1}{2}$</code> → <MathText text="$\frac{1}{2}$" /></p>
                        <p><code>$x^2 + y^2$</code> → <MathText text="$x^2 + y^2$" /></p>
                        <p><code>$\sqrt{16}$</code> → <MathText text="$\sqrt{16}$" /></p>
                    </div>
                    <div class="bg-white rounded p-2">
                        <p class="font-medium">Kimya/Fizika nümunələri:</p>
                        <p><code>$\ce{H2O}$</code> → <MathText text="$\ce{H2O}$" /></p>
                        <p><code>$F = ma$</code> → <MathText text="$F = ma$" /></p>
                        <p><code>$E = mc^2$</code> → <MathText text="$E = mc^2$" /></p>
                    </div>
                </div>
            </div>
        </div>

        <div class="bg-white overflow-hidden shadow-sm rounded-lg">
            <form @submit.prevent="emit('submit')" class="p-6 space-y-6">
                <div
                    v-if="attemptUsage > 0"
                    class="rounded-md border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800"
                >
                    Bu sual <strong>{{ attemptUsage }}</strong> şagird cəhdində istifadə olunub.
                    Mətni dəyişsəniz, həmin cəhdlərin nəticə səhifələrində də yeni mətn görünəcək
                    (ballar yenidən hesablanmır). Tarixçəni toxunulmaz saxlamaq üçün "Kopyala və
                    əvəzlə" seçimindən istifadə edin.
                </div>

                <!-- Sual növü -->
                <div>
                    <InputLabel value="Sual Növü" />
                    <div class="mt-2 grid gap-2 sm:grid-cols-3">
                        <label class="flex items-start gap-2 cursor-pointer rounded-md border border-gray-200 p-3 hover:border-indigo-300">
                            <input type="radio" v-model="form.type" value="multiple_choice" class="mt-1 w-4 h-4 text-indigo-600 border-gray-300 focus:ring-indigo-500" />
                            <span>
                                <span class="block text-sm font-medium text-gray-800">Test</span>
                                <span class="block text-xs text-gray-500">{{ optionCount }} variant, biri düzgün</span>
                            </span>
                        </label>
                        <label class="flex items-start gap-2 cursor-pointer rounded-md border border-gray-200 p-3 hover:border-indigo-300">
                            <input type="radio" v-model="form.type" value="open_coded" class="mt-1 w-4 h-4 text-indigo-600 border-gray-300 focus:ring-indigo-500" />
                            <span>
                                <span class="block text-sm font-medium text-gray-800">Qısa cavab</span>
                                <span class="block text-xs text-gray-500">Rəqəm/qısa mətn, avtomatik yoxlanır</span>
                            </span>
                        </label>
                        <label class="flex items-start gap-2 cursor-pointer rounded-md border border-gray-200 p-3 hover:border-indigo-300">
                            <input type="radio" v-model="form.type" value="open_written" class="mt-1 w-4 h-4 text-indigo-600 border-gray-300 focus:ring-indigo-500" />
                            <span>
                                <span class="block text-sm font-medium text-gray-800">Açıq (həll yazılır)</span>
                                <span class="block text-xs text-gray-500">Admin əl ilə qiymətləndirir</span>
                            </span>
                        </label>
                    </div>
                    <InputError :message="form.errors.type" class="mt-2" />
                </div>

                <!-- Sual mətni -->
                <div>
                    <div class="flex items-center justify-between mb-1">
                        <InputLabel for="question_text" value="Sual Mətni" />
                        <button type="button" @click="previewMode = !previewMode" class="text-xs text-indigo-600 hover:text-indigo-800">
                            {{ previewMode ? 'Redaktə' : 'Önizləmə' }}
                        </button>
                    </div>

                    <div class="flex flex-wrap gap-1 mb-2">
                        <button
                            v-for="shortcut in mathShortcuts"
                            :key="shortcut.label"
                            type="button"
                            @click="insertFormula(shortcut.formula)"
                            class="px-2 py-0.5 text-xs bg-gray-100 hover:bg-indigo-100 hover:text-indigo-700 rounded border border-gray-200"
                        >
                            {{ shortcut.label }}
                        </button>
                    </div>

                    <textarea
                        v-if="!previewMode"
                        id="question_text"
                        v-model="form.question_text"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 font-mono text-sm"
                        rows="4"
                        required
                        placeholder="Sualı daxil edin... Formula üçün $...$ istifadə edin, məs: $x^2 + 3x = 0$"
                    ></textarea>
                    <div v-else class="mt-1 min-h-[100px] p-3 border border-gray-300 rounded-md bg-gray-50">
                        <MathText :text="form.question_text || 'Mətn yoxdur...'" class="text-gray-900" />
                    </div>
                    <InputError :message="form.errors.question_text" class="mt-2" />
                </div>

                <!-- Sual şəkli -->
                <div>
                    <InputLabel value="Şəkil (istəyə bağlı)" />
                    <div class="mt-2">
                        <div v-if="imagePreview || visibleExistingImage" class="mb-3">
                            <img :src="imagePreview || existingImageUrl" alt="Sual şəkli" class="max-w-xs rounded-lg border border-gray-200" />
                            <button type="button" @click="removeImage" class="mt-2 text-sm text-red-600 hover:text-red-800">
                                Şəkli sil
                            </button>
                        </div>
                        <input
                            v-else
                            type="file"
                            accept="image/*"
                            @change="handleImageChange"
                            class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100"
                        />
                        <InputError :message="form.errors.question_image" class="mt-2" />
                    </div>
                </div>

                <!-- Variantlar -->
                <div v-if="isMultipleChoice">
                    <InputLabel :value="`Cavab Variantları (${optionCount})`" />

                    <div class="mt-3 space-y-3">
                        <div v-for="(option, index) in form.options" :key="option.option_letter">
                            <div class="flex items-center gap-3">
                                <button
                                    type="button"
                                    @click="setCorrectOption(index)"
                                    :title="'Düzgün cavab: ' + option.option_letter"
                                    :class="[
                                        'flex-shrink-0 w-8 h-8 rounded-full flex items-center justify-center text-sm font-medium border-2 transition',
                                        option.is_correct
                                            ? 'bg-green-500 text-white border-green-500'
                                            : 'bg-white text-gray-500 border-gray-300 hover:border-green-400',
                                    ]"
                                >
                                    {{ option.option_letter }}
                                </button>
                                <TextInput
                                    v-model="option.option_text"
                                    type="text"
                                    class="flex-1 font-mono text-sm"
                                    :placeholder="`Variant ${option.option_letter} — formula üçün $...$`"
                                    required
                                />
                                <input
                                    type="file"
                                    accept="image/*"
                                    @change="(event) => handleOptionImageChange(event, index)"
                                    class="w-40 text-xs text-gray-500 file:mr-2 file:py-1 file:px-2 file:rounded file:border-0 file:text-xs file:bg-gray-100"
                                />
                            </div>
                            <div v-if="option.option_text && option.option_text.includes('$')" class="ml-11 mt-1 text-xs text-gray-500 flex items-center gap-1">
                                <span>Önizləmə:</span>
                                <MathText :text="option.option_text" />
                            </div>
                        </div>
                    </div>

                    <p class="mt-2 text-sm text-gray-500">Düzgün cavabı seçmək üçün hərf düyməsinə klikləyin.</p>
                    <InputError :message="form.errors.options" class="mt-2" />
                </div>

                <!-- Qısa cavab -->
                <div v-if="isOpenCoded">
                    <InputLabel value="Düzgün cavab(lar)" />
                    <p class="mt-1 text-xs text-gray-500">
                        Rəqəm cavabları ədəd kimi müqayisə olunur: <code>0,5</code> yazsanız
                        <code>0.5</code>, <code>.5</code> və <code>1/2</code> də qəbul olunur.
                        Əlavə sətir yalnız ədəd olmayan alternativlər üçün lazımdır (məsələn <code>x=2</code>).
                    </p>

                    <div class="mt-3 space-y-2">
                        <div v-for="(answer, index) in form.accepted_answers" :key="index" class="flex items-center gap-2">
                            <TextInput
                                v-model="form.accepted_answers[index]"
                                type="text"
                                class="flex-1 font-mono text-sm"
                                placeholder="Məs: 0,5"
                            />
                            <button
                                v-if="form.accepted_answers.length > 1"
                                type="button"
                                @click="removeAcceptedAnswer(index)"
                                class="text-red-500 hover:text-red-700 text-sm"
                            >
                                Sil
                            </button>
                        </div>
                    </div>

                    <button type="button" @click="addAcceptedAnswer" class="mt-2 text-sm text-indigo-600 hover:text-indigo-800">
                        + Alternativ cavab
                    </button>
                    <InputError :message="form.errors.accepted_answers" class="mt-2" />
                </div>

                <div v-if="form.type === 'open_written'" class="rounded-md bg-amber-50 border border-amber-200 p-3 text-sm text-amber-800">
                    Bu sual avtomatik yoxlanmır: şagird həllini yazır, siz admin paneldən qiymətləndirirsiniz.
                </div>

                <!-- Bank məlumatları -->
                <div class="grid gap-4 sm:grid-cols-3">
                    <div>
                        <InputLabel for="topic_id" value="Mövzu (istəyə bağlı)" />
                        <select
                            id="topic_id"
                            v-model="form.topic_id"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                        >
                            <option :value="null">— seçilməyib —</option>
                            <option v-for="topic in topics" :key="topic.id" :value="topic.id">
                                {{ topic.name }}<template v-if="topic.quarter"> ({{ topic.quarter }}-ci rüb)</template>
                            </option>
                        </select>
                        <InputError :message="form.errors.topic_id" class="mt-2" />
                    </div>

                    <div>
                        <InputLabel for="difficulty" value="Çətinlik" />
                        <select
                            id="difficulty"
                            v-model="form.difficulty"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                        >
                            <option value="easy">Sadə</option>
                            <option value="medium">Orta</option>
                            <option value="hard">Mürəkkəb</option>
                        </select>
                        <InputError :message="form.errors.difficulty" class="mt-2" />
                    </div>

                    <div>
                        <InputLabel for="source" value="Mənbə (istəyə bağlı)" />
                        <TextInput id="source" v-model="form.source" type="text" class="mt-1 block w-full"
                            placeholder="Məs: DİM 2024" />
                        <InputError :message="form.errors.source" class="mt-2" />
                    </div>
                </div>

                <!-- İzah -->
                <div>
                    <InputLabel for="explanation" value="İzah (istəyə bağlı, nəticə səhifəsində göstərilir)" />
                    <textarea
                        id="explanation"
                        v-model="form.explanation"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                        rows="2"
                    ></textarea>
                    <InputError :message="form.errors.explanation" class="mt-2" />
                </div>

                <div class="flex justify-end gap-4 pt-4 border-t border-gray-200">
                    <Link :href="cancelHref" class="px-4 py-2 text-gray-700 hover:text-gray-900">Ləğv et</Link>
                    <PrimaryButton :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                        {{ submitLabel }}
                    </PrimaryButton>
                </div>
            </form>
        </div>
    </div>
</template>

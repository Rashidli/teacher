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
    // İmtahanın kateqoriyasında icazəli sual tipləri (config/questions.php)
    allowedTypes: { type: Array, default: () => ['multiple_choice', 'open_coded', 'open_written'] },
    // Mətn/mənbə əsaslı yazılı tapşırıqlarda seçilə bilən mətnlər
    passages: { type: Array, default: () => [] },
});

const emit = defineEmits(['submit']);

const LETTERS = ['A', 'B', 'C', 'D', 'E'];

const optionCount = computed(() => props.exam.options_per_question ?? 5);
/** Sual tipləri: imtahanın kateqoriyasında icazəli olanlar (config/questions.php) */
const TYPE_LABELS = {
    multiple_choice: { label: 'Test', hint: () => `${optionCount.value} variant, biri düzgün` },
    open_coded: { label: 'Qısa cavab', hint: () => 'Rəqəm/qısa mətn, avtomatik yoxlanır' },
    open_written: { label: 'Açıq (həll yazılır)', hint: () => 'Admin əl ilə qiymətləndirir' },
};

const typeChoices = computed(() => props.allowedTypes
    .filter((type) => TYPE_LABELS[type])
    .map((type) => ({
        value: type,
        label: TYPE_LABELS[type].label,
        hint: TYPE_LABELS[type].hint(),
    })));

const isMultipleChoice = computed(() => props.form.type === 'multiple_choice');
const isOpenCoded = computed(() => props.form.type === 'open_coded');

/*
 * DİM-in açıq tapşırıq alt növləri.
 *
 * Kodlaşdırılanda alt növ YOXLAMA QAYDASINI seçir (hamısı avtomatik yoxlanır, bal 1),
 * yazılıda isə yalnız məlumat/filtr üçündür — bal qaydası dəyişmir.
 */
const SUBTYPES = {
    open_coded: [
        { value: 'numeric', label: 'Hesablama', hint: 'Rəqəm və ya qısa mətn cavabı' },
        { value: 'multi_select', label: 'Seçim', hint: 'Bir neçə düzgün variant' },
        { value: 'ordering', label: 'Ardıcıllıq', hint: 'Xronologiya və ya düzülüş' },
        { value: 'matching', label: 'Uyğunluq', hint: 'Sol-sağ cütlər' },
    ],
    open_written: [
        { value: 'serbest', label: 'Sərbəst', hint: 'I qrup: riyaziyyat, fizika, kimya, informatika' },
        { value: 'situasiya', label: 'Situasiya', hint: 'Coğrafiya, biologiya, riyaziyyat II qrup' },
        { value: 'metn', label: 'Mətnə əsaslanan', hint: 'III qrup: dil və ədəbiyyat' },
        { value: 'menbe', label: 'Mənbəyə əsaslanan', hint: 'II–III qrup: tarix' },
        { value: 'isbat', label: 'İsbat', hint: 'I qrup riyaziyyat' },
    ],
};

const subtypeChoices = computed(() => SUBTYPES[props.form.type] ?? []);

const subtype = computed(() => (isOpenCoded.value ? (props.form.subtype || 'numeric') : props.form.subtype));

const isNumeric = computed(() => isOpenCoded.value && subtype.value === 'numeric');
const isMultiSelect = computed(() => subtype.value === 'multi_select');
const isOrdering = computed(() => subtype.value === 'ordering');
const isMatching = computed(() => subtype.value === 'matching');

// Seçim və ardıcıllıqda variantlar cavabın özüdür; uyğunluqda isə sol-sağ cütlər
const usesOptionList = computed(() => isMultiSelect.value || isOrdering.value);

// Mətn/mənbə əsaslı yazılıda bir mətnə bir neçə sual bağlana bilər
const usesPassage = computed(() => ['metn', 'menbe'].includes(props.form.subtype));

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

const blankOption = (index) => ({
    option_letter: LETTERS[index],
    option_text: '',
    option_image: null,
    is_correct: false,
});

/*
 * Variantlar həmişə lazımi sayda olmalıdır.
 *
 * Testdə say imtahandan gəlir (dəyişmir). Seçim və ardıcıllıqda isə say sərbəstdir —
 * admin bənd əlavə edib silə bilir, ona görə yalnız minimum (2) təmin olunur.
 */
const syncOptions = () => {
    if (!isMultipleChoice.value && !usesOptionList.value) {
        return;
    }

    const options = props.form.options ?? [];
    const target = isMultipleChoice.value ? optionCount.value : Math.max(2, options.length);

    while (options.length < target) {
        options.push(blankOption(options.length));
    }

    options.length = target;
    options.forEach((option, index) => { option.option_letter = LETTERS[index]; });

    // Testdə düz bir düzgün variant olmalıdır; seçimdə isə bir neçəsi ola bilər
    if (isMultipleChoice.value && !options.some((option) => option.is_correct)) {
        options[0].is_correct = true;
    }

    props.form.options = options;
};

watch(() => props.form.type, () => {
    // Tip dəyişəndə alt növ də tipə uyğunlaşır: köhnə alt növ suala yapışıb qalmasın
    const choices = SUBTYPES[props.form.type] ?? [];

    if (!choices.some((choice) => choice.value === props.form.subtype)) {
        props.form.subtype = choices.length ? choices[0].value : null;
    }

    syncOptions();
}, { immediate: true });

watch(() => props.form.subtype, () => {
    syncOptions();

    if (isMatching.value && (props.form.pairs ?? []).length < 2) {
        props.form.pairs = [{ left: '', right: '' }, { left: '', right: '' }];
    }
});

const setCorrectOption = (index) => {
    props.form.options.forEach((option, i) => { option.is_correct = i === index; });
};

/** Seçimdə bir neçə düzgün variant ola bilər */
const toggleCorrectOption = (index) => {
    props.form.options[index].is_correct = !props.form.options[index].is_correct;
};

const addOption = () => {
    if (props.form.options.length < LETTERS.length) {
        props.form.options.push(blankOption(props.form.options.length));
        syncOptions();
    }
};

const removeOption = (index) => {
    if (props.form.options.length > 2) {
        props.form.options.splice(index, 1);
        syncOptions();
    }
};

/*
 * Ardıcıllıqda variantların SIRASI düzgün cavabdır: admin bəndləri burada düzgün sıra ilə
 * düzür, şagird tərəfdə isə siyahı qarışdırılır.
 */
const moveOption = (index, delta) => {
    const target = index + delta;

    if (target < 0 || target >= props.form.options.length) {
        return;
    }

    const options = props.form.options;
    [options[index], options[target]] = [options[target], options[index]];
    syncOptions();
};

/* --------------------------------------------------------- uyğunluq cütləri */

const addPair = () => {
    if ((props.form.pairs ?? []).length < 8) {
        props.form.pairs.push({ left: '', right: '' });
    }
};

const removePair = (index) => {
    if (props.form.pairs.length > 2) {
        props.form.pairs.splice(index, 1);
    }
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

                <!-- Sual növü: yalnız bu imtahan növündə icazəli olanlar -->
                <div>
                    <InputLabel value="Sual Növü" />
                    <div class="mt-2 grid gap-2" :class="typeChoices.length > 1 ? 'sm:grid-cols-3' : ''">
                        <label
                            v-for="choice in typeChoices"
                            :key="choice.value"
                            class="flex items-start gap-2 cursor-pointer rounded-md border border-gray-200 p-3 hover:border-indigo-300"
                        >
                            <input
                                type="radio"
                                v-model="form.type"
                                :value="choice.value"
                                class="mt-1 w-4 h-4 text-indigo-600 border-gray-300 focus:ring-indigo-500"
                            />
                            <span>
                                <span class="block text-sm font-medium text-gray-800">{{ choice.label }}</span>
                                <span class="block text-xs text-gray-500">{{ choice.hint }}</span>
                            </span>
                        </label>
                    </div>
                    <p v-if="typeChoices.length === 1" class="mt-2 text-xs text-gray-500">
                        Bu imtahan növündə yalnız “{{ typeChoices[0].label }}” sualı işlənir.
                    </p>
                    <InputError :message="form.errors.type" class="mt-2" />
                </div>

                <!--
                    Alt növ. Kodlaşdırılanda yoxlama qaydasını seçir (hamısı avtomatik
                    yoxlanır, bal 1), yazılıda isə yalnız məlumat/filtr üçündür.
                -->
                <div v-if="subtypeChoices.length">
                    <InputLabel :value="isOpenCoded ? 'Tapşırığın növü' : 'Yazılı tapşırığın növü'" />
                    <p v-if="!isOpenCoded" class="mt-1 text-xs text-gray-500">
                        Bal qaydasını dəyişmir — yalnız məlumat və filtr üçündür.
                    </p>
                    <div class="mt-2 grid gap-2 sm:grid-cols-2">
                        <label
                            v-for="choice in subtypeChoices"
                            :key="choice.value"
                            class="flex items-start gap-2 cursor-pointer rounded-md border border-gray-200 p-3 hover:border-indigo-300"
                        >
                            <input
                                type="radio"
                                v-model="form.subtype"
                                :value="choice.value"
                                class="mt-1 w-4 h-4 text-indigo-600 border-gray-300 focus:ring-indigo-500"
                            />
                            <span>
                                <span class="block text-sm font-medium text-gray-800">{{ choice.label }}</span>
                                <span class="block text-xs text-gray-500">{{ choice.hint }}</span>
                            </span>
                        </label>
                    </div>
                    <InputError :message="form.errors.subtype" class="mt-2" />
                </div>

                <!-- Mətn/mənbə: bir mətnə bir neçə sual bağlana bilər -->
                <div v-if="usesPassage">
                    <InputLabel for="passage_id" value="Mətn / mənbə" />
                    <select
                        id="passage_id"
                        v-model="form.passage_id"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm"
                    >
                        <option :value="null">— seçilməyib —</option>
                        <option v-for="passage in passages" :key="passage.id" :value="passage.id">
                            {{ passage.title }}
                        </option>
                    </select>
                    <p class="mt-1 text-xs text-gray-500">
                        Eyni mətn bir neçə suala bağlana bilər: şagird mətni hər sualın yanında görür.
                        Yeni mətn “Mətnlər” bölməsindən əlavə olunur.
                    </p>
                    <InputError :message="form.errors.passage_id" class="mt-2" />
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

                    <!-- Şəkil məzmunun özüdürsə (yol nişanı, sxem) ekran oxuyucusu üçün təsvir lazımdır -->
                    <div v-if="imagePreview || visibleExistingImage" class="mt-3">
                        <InputLabel for="question_image_alt" value="Şəklin təsviri (alt mətni)" />
                        <TextInput
                            id="question_image_alt"
                            v-model="form.question_image_alt"
                            type="text"
                            class="mt-1 block w-full"
                            maxlength="255"
                            placeholder="Məs: Yol nişanı"
                        />
                        <p class="mt-1 text-xs text-gray-500">
                            Şəkli görməyən istifadəçi üçün. <strong>Yalnız şəklin NÖVÜNÜ yaz,
                            məzmununu açma</strong> — əks halda şagird şəklə baxmadan cavabı
                            tapar. Düzgün: “Yol nişanı”, “Yolayrıcı sxemi”, “Funksiyanın qrafiki”.
                            Səhv: “dairəvi nişan, qırmızı fon, ağ üfüqi zolaq” (bu, birbaşa
                            cavabdır).
                        </p>
                        <InputError :message="form.errors.question_image_alt" class="mt-2" />
                    </div>
                </div>

                <!-- Variantlar: test, seçim və ardıcıllıq -->
                <div v-if="isMultipleChoice || usesOptionList">
                    <InputLabel :value="isMultipleChoice
                        ? `Cavab Variantları (${optionCount})`
                        : (isOrdering ? 'Bəndlər — DÜZGÜN ardıcıllıqla' : 'Variantlar — düzgün olanları işarələ')" />

                    <p v-if="isOrdering" class="mt-1 text-xs text-gray-500">
                        Bəndləri burada düzgün sıra ilə düzün. Şagird onları qarışıq görür və
                        özü sıralayır.
                    </p>
                    <p v-else-if="isMultiSelect" class="mt-1 text-xs text-gray-500">
                        Ən azı iki düzgün variant olmalıdır (hamısı düzgün ola bilməz).
                    </p>

                    <div class="mt-3 space-y-3">
                        <div v-for="(option, index) in form.options" :key="option.option_letter">
                            <div class="flex items-center gap-3">
                                <!-- Ardıcıllıqda "düzgün variant" yoxdur: sıra özü cavabdır -->
                                <span
                                    v-if="isOrdering"
                                    class="flex-shrink-0 w-8 h-8 rounded-full flex items-center justify-center text-sm font-medium border-2 border-gray-300 bg-gray-50 text-gray-600"
                                >{{ index + 1 }}</span>
                                <button
                                    v-else
                                    type="button"
                                    @click="isMultiSelect ? toggleCorrectOption(index) : setCorrectOption(index)"
                                    :title="'Düzgün cavab: ' + option.option_letter"
                                    :aria-pressed="option.is_correct"
                                    :class="[
                                        'flex-shrink-0 w-8 h-8 flex items-center justify-center text-sm font-medium border-2 transition',
                                        isMultiSelect ? 'rounded-md' : 'rounded-full',
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
                                    v-if="isMultipleChoice"
                                    type="file"
                                    accept="image/*"
                                    @change="(event) => handleOptionImageChange(event, index)"
                                    class="w-40 text-xs text-gray-500 file:mr-2 file:py-1 file:px-2 file:rounded file:border-0 file:text-xs file:bg-gray-100"
                                />

                                <!-- Ardıcıllıqda sıra düyməsi, seçimdə isə bənd silmə -->
                                <template v-if="isOrdering">
                                    <button
                                        type="button"
                                        class="px-2 py-1 text-sm text-gray-500 hover:text-indigo-700 disabled:opacity-30"
                                        :disabled="index === 0"
                                        aria-label="Yuxarı"
                                        @click="moveOption(index, -1)"
                                    >↑</button>
                                    <button
                                        type="button"
                                        class="px-2 py-1 text-sm text-gray-500 hover:text-indigo-700 disabled:opacity-30"
                                        :disabled="index === form.options.length - 1"
                                        aria-label="Aşağı"
                                        @click="moveOption(index, 1)"
                                    >↓</button>
                                </template>

                                <button
                                    v-if="usesOptionList && form.options.length > 2"
                                    type="button"
                                    class="px-2 py-1 text-sm text-red-500 hover:text-red-700"
                                    @click="removeOption(index)"
                                >Sil</button>
                            </div>
                            <div v-if="option.option_text && option.option_text.includes('$')" class="ml-11 mt-1 text-xs text-gray-500 flex items-center gap-1">
                                <span>Önizləmə:</span>
                                <MathText :text="option.option_text" />
                            </div>
                        </div>
                    </div>

                    <button
                        v-if="usesOptionList && form.options.length < 5"
                        type="button"
                        class="mt-2 text-sm text-indigo-600 hover:text-indigo-800"
                        @click="addOption"
                    >+ Bənd əlavə et</button>

                    <p v-if="!isOrdering" class="mt-2 text-sm text-gray-500">
                        Düzgün cavabı seçmək üçün hərf düyməsinə klikləyin.
                    </p>
                    <InputError :message="form.errors.options" class="mt-2" />
                </div>

                <!-- Uyğunluq: sol-sağ cütlər SIRALI saxlanılır, şagird tərəfdə sağ sütun qarışır -->
                <div v-if="isMatching">
                    <InputLabel value="Uyğunluq cütləri" />
                    <p class="mt-1 text-xs text-gray-500">
                        Hər sətirdə bir-birinə uyğun gələn cüt yazılır. Şagird sağ sütunu
                        qarışıq görür və hər sol bəndə uyğun olanı seçir.
                    </p>

                    <div class="mt-3 space-y-2">
                        <div v-for="(pair, index) in form.pairs" :key="index" class="flex items-center gap-2">
                            <span class="w-6 text-sm text-gray-500">{{ index + 1 }}.</span>
                            <TextInput v-model="pair.left" type="text" class="flex-1 text-sm" placeholder="Sol bənd" />
                            <span class="text-gray-400" aria-hidden="true">→</span>
                            <TextInput v-model="pair.right" type="text" class="flex-1 text-sm" placeholder="Sağ bənd" />
                            <button
                                v-if="form.pairs.length > 2"
                                type="button"
                                class="px-2 py-1 text-sm text-red-500 hover:text-red-700"
                                @click="removePair(index)"
                            >Sil</button>
                        </div>
                    </div>

                    <button
                        v-if="form.pairs.length < 8"
                        type="button"
                        class="mt-2 text-sm text-indigo-600 hover:text-indigo-800"
                        @click="addPair"
                    >+ Cüt əlavə et</button>
                    <InputError :message="form.errors.pairs" class="mt-2" />
                </div>

                <!-- Hesablama: etalon cavab(lar). Digər alt növlərdə cavab variantlardan hesablanır -->
                <div v-if="isNumeric">
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

                <!-- Qiymətləndirmə meyarı: yalnız açıq yazılı sual üçün -->
                <div v-if="form.type === 'open_written'">
                    <InputLabel for="grading_rubric" value="Qiymətləndirmə meyarı (düzgün cavab və tələblər)" />
                    <textarea
                        id="grading_rubric"
                        v-model="form.grading_rubric"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                        rows="4"
                        placeholder="Məs: Tam cavab üçün (1) düsturun yazılması, (2) düzgün hesablama, (3) nəticənin vahidi. Yalnız düstur — 1/2, yalnız nəticə — 1/3."
                    ></textarea>
                    <p class="mt-1 text-xs text-gray-500">
                        Avtomatik qiymətləndirmə məhz bu mətnə görə işləyir. Boş qalsa, cavab
                        AI-yə göndərilmir və əl ilə yoxlanır. Nəticə səhifəsində şagirdə də göstərilir.
                    </p>
                    <InputError :message="form.errors.grading_rubric" class="mt-2" />
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

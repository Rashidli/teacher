<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import MathText from '@/Components/MathText.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

const props = defineProps({
    exam: Object,
    question: Object,
    allowedTypes: { type: Array, default: () => ['multiple_choice', 'open_coded', 'open_written'] },
});

const form = useForm({
    question_text: props.question.question_text,
    type: props.question.type,
    question_image: null,
    question_image_alt: '',
    remove_image: false,
    options: props.question.options?.length
        ? props.question.options.map((opt, index) => ({
            id: opt.id,
            option_letter: opt.option_letter || String.fromCharCode(65 + index),
            option_text: opt.option_text,
            is_correct: opt.is_correct,
        }))
        : [
            { option_letter: 'A', option_text: '', is_correct: true },
            { option_letter: 'B', option_text: '', is_correct: false },
        ],
});

const imagePreview = ref(null);
const existingImage = ref(props.question.question_image);
const showMathHelp = ref(false);
const previewMode = ref(false);

const handleImageChange = (event) => {
    const file = event.target.files[0];
    if (file) {
        form.question_image = file;
        form.remove_image = false;
        const reader = new FileReader();
        reader.onload = (e) => { imagePreview.value = e.target.result; };
        reader.readAsDataURL(file);
    }
};

const removeImage = () => {
    form.question_image = null;
    form.remove_image = true;
    imagePreview.value = null;
    existingImage.value = null;
};

const setCorrectOption = (index) => {
    form.options.forEach((opt, i) => { opt.is_correct = i === index; });
};

const addOption = () => {
    if (form.options.length < 5) {
        const letter = String.fromCharCode(65 + form.options.length);
        form.options.push({ option_letter: letter, option_text: '', is_correct: false });
    }
};

const removeOption = (index) => {
    if (form.options.length > 2) {
        const wasCorrect = form.options[index].is_correct;
        form.options.splice(index, 1);
        form.options.forEach((opt, i) => { opt.option_letter = String.fromCharCode(65 + i); });
        if (wasCorrect && form.options.length > 0) form.options[0].is_correct = true;
    }
};

const isMultipleChoice = computed(() => form.type === 'multiple_choice');

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

const submit = () => {
    form.post(route('teacher.exams.questions.update', [props.exam.id, props.question.id]), {
        forceFormData: true,
        _method: 'PUT',
    });
};
</script>

<template>
    <Head title="Sualı Redaktə Et" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">
                    Sualı Redaktə Et
                </h2>
                <Link :href="route('teacher.exams.show', exam.id)" class="text-sm text-indigo-600 hover:text-indigo-800">
                    Geri
                </Link>
            </div>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-3xl sm:px-6 lg:px-8 space-y-4">

                <!-- Formula Yardım Paneli -->
                <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                    <button type="button" @click="showMathHelp = !showMathHelp"
                        class="flex items-center gap-2 text-sm font-medium text-blue-700 w-full">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 11h.01M12 11h.01M15 11h.01M4 19h16a2 2 0 002-2V7a2 2 0 00-2-2H4a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                        </svg>
                        Formula daxil etmə qaydası
                        <svg :class="['w-4 h-4 ml-auto transition', showMathHelp ? 'rotate-180' : '']" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
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
                    <form @submit.prevent="submit" class="p-6 space-y-6">

                        <!-- Sual Növü -->
                        <div>
                            <InputLabel value="Sual Növü" />
                            <div class="mt-2 flex gap-4">
                                <label class="flex items-center cursor-pointer">
                                    <input type="radio" v-model="form.type" value="multiple_choice" class="w-4 h-4 text-indigo-600 border-gray-300 focus:ring-indigo-500" />
                                    <span class="ml-2 text-sm text-gray-700">Çoxseçimli</span>
                                </label>
                                <label class="flex items-center cursor-pointer">
                                    <input type="radio" v-model="form.type" value="open_ended" class="w-4 h-4 text-indigo-600 border-gray-300 focus:ring-indigo-500" />
                                    <span class="ml-2 text-sm text-gray-700">Açıq cavab</span>
                                </label>
                            </div>
                            <InputError :message="form.errors.type" class="mt-2" />
                        </div>

                        <!-- Sual Mətni -->
                        <div>
                            <div class="flex items-center justify-between mb-1">
                                <InputLabel for="question_text" value="Sual Mətni" />
                                <button type="button" @click="previewMode = !previewMode"
                                    class="text-xs text-indigo-600 hover:text-indigo-800 flex items-center gap-1">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                    </svg>
                                    {{ previewMode ? 'Redaktə' : 'Önizləmə' }}
                                </button>
                            </div>

                            <!-- Tez formula düymələri -->
                            <div class="flex flex-wrap gap-1 mb-2">
                                <button v-for="s in mathShortcuts" :key="s.label" type="button"
                                    @click="form.question_text += ` $${s.formula}$ `"
                                    class="px-2 py-0.5 text-xs bg-gray-100 hover:bg-indigo-100 hover:text-indigo-700 rounded border border-gray-200">
                                    {{ s.label }}
                                </button>
                            </div>

                            <div v-if="!previewMode">
                                <textarea id="question_text" v-model="form.question_text"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 font-mono text-sm"
                                    rows="4" required
                                    placeholder="Formula üçün $...$ istifadə edin, məs: $x^2 + 3x = 0$">
                                </textarea>
                            </div>
                            <div v-else class="mt-1 min-h-[100px] p-3 border border-gray-300 rounded-md bg-gray-50">
                                <MathText :text="form.question_text || 'Mətn yoxdur...'" class="text-gray-900" />
                            </div>
                            <InputError :message="form.errors.question_text" class="mt-2" />
                        </div>

                        <!-- Şəkil -->
                        <div>
                            <InputLabel value="Şəkil (istəyə bağlı)" />
                            <div class="mt-2">
                                <div v-if="imagePreview || existingImage" class="mb-3">
                                    <img :src="imagePreview || `/storage/${existingImage}`" alt="Preview" class="max-w-xs rounded-lg border border-gray-200" />
                                    <button type="button" @click="removeImage" class="mt-2 text-sm text-red-600 hover:text-red-800">Şəkili Sil</button>
                                </div>
                                <input v-else type="file" @change="handleImageChange" accept="image/*"
                                    class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100" />
                            </div>
                            <InputError :message="form.errors.question_image" class="mt-2" />
                        </div>

                        <!-- Variantlar -->
                        <div v-if="isMultipleChoice">
                            <div class="flex items-center justify-between mb-3">
                                <InputLabel value="Cavab Variantları" />
                                <button v-if="form.options.length < 5" type="button" @click="addOption"
                                    class="text-sm text-indigo-600 hover:text-indigo-800">
                                    + Variant Əlavə Et
                                </button>
                            </div>

                            <div class="space-y-3">
                                <div v-for="(option, index) in form.options" :key="index">
                                    <div class="flex items-center gap-3">
                                        <button type="button" @click="setCorrectOption(index)"
                                            :class="[
                                                'flex-shrink-0 w-8 h-8 rounded-full flex items-center justify-center text-sm font-medium border-2 transition',
                                                option.is_correct
                                                    ? 'bg-green-500 text-white border-green-500'
                                                    : 'bg-white text-gray-500 border-gray-300 hover:border-green-400'
                                            ]">
                                            {{ option.option_letter }}
                                        </button>
                                        <TextInput v-model="option.option_text" type="text" class="flex-1 font-mono text-sm"
                                            :placeholder="`Variant ${option.option_letter} — formula üçün $...$ istifadə edin`" required />
                                        <button v-if="form.options.length > 2" type="button" @click="removeOption(index)" class="text-red-500 hover:text-red-700">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                            </svg>
                                        </button>
                                    </div>
                                    <div v-if="option.option_text && option.option_text.includes('$')"
                                        class="ml-11 mt-1 text-xs text-gray-500 flex items-center gap-1">
                                        <span>Önizləmə:</span>
                                        <MathText :text="option.option_text" />
                                    </div>
                                </div>
                            </div>
                            <p class="mt-2 text-sm text-gray-500">Düzgün cavabı seçmək üçün hərf düyməsinə klikləyin</p>
                            <InputError :message="form.errors.options" class="mt-2" />
                        </div>

                        <div class="flex justify-end gap-4 pt-4 border-t border-gray-200">
                            <Link :href="route('teacher.exams.show', exam.id)" class="px-4 py-2 text-gray-700 hover:text-gray-900">Ləğv et</Link>
                            <PrimaryButton :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                                Yadda Saxla
                            </PrimaryButton>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

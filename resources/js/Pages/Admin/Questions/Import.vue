<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import MathText from '@/Components/MathText.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    exam: { type: Object, required: true },
    // null: fayl hələ yüklənməyib. Massiv: önizləmə nəticəsi.
    rows: { type: Array, default: null },
    token: { type: String, default: null },
});

const TYPE_LABELS = {
    multiple_choice: 'Test',
    open_coded: 'Qısa cavab',
    open_written: 'Açıq',
};

const uploadForm = useForm({ file: null });
const confirmForm = useForm({ token: props.token });

const invalidRows = computed(() => (props.rows ?? []).filter((row) => row.errors.length > 0));
const validCount = computed(() => (props.rows ?? []).length - invalidRows.value.length);
const canImport = computed(() => props.rows?.length > 0 && invalidRows.value.length === 0);

const upload = () => {
    uploadForm.post(route('admin.exams.questions.import.preview', props.exam.id), {
        forceFormData: true,
    });
};

const confirmImport = () => {
    confirmForm.token = props.token;
    confirmForm.post(route('admin.exams.questions.import.store', props.exam.id));
};
</script>

<template>
    <Head title="Toplu sual importu" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">
                    Toplu sual importu: {{ exam.title }}
                </h2>
                <Link :href="route('admin.exams.show', exam.id)" class="text-sm text-indigo-600 hover:text-indigo-800">
                    Geri
                </Link>
            </div>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-5xl sm:px-6 lg:px-8 space-y-6">
                <!-- Addım 1: şablon və fayl -->
                <div class="bg-white shadow-sm rounded-lg p-6">
                    <h3 class="text-lg font-semibold text-gray-900">1. Şablonu doldurun</h3>
                    <p class="mt-1 text-sm text-gray-600">
                        Şablonda bu imtahan üçün lazım olan sütunlar var
                        ({{ exam.options_per_question }} variant), nümunə sətirlər və izah vərəqi.
                    </p>
                    <a
                        :href="route('admin.exams.questions.import.template', exam.id)"
                        class="mt-3 inline-block px-4 py-2 bg-gray-100 text-gray-800 text-sm rounded-lg hover:bg-gray-200"
                    >
                        Şablonu yüklə (.xlsx)
                    </a>

                    <h3 class="mt-6 text-lg font-semibold text-gray-900">2. Faylı yükləyin</h3>
                    <form @submit.prevent="upload" class="mt-3 flex flex-wrap items-center gap-3">
                        <input
                            type="file"
                            accept=".xlsx,.xls,.csv"
                            @input="uploadForm.file = $event.target.files[0]"
                            class="block text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100"
                        />
                        <PrimaryButton :class="{ 'opacity-25': uploadForm.processing }" :disabled="uploadForm.processing || !uploadForm.file">
                            Yoxla
                        </PrimaryButton>
                    </form>
                    <p class="mt-2 text-xs text-gray-500">.xlsx, .xls və ya UTF-8 .csv — maksimum 10 MB.</p>
                    <InputError :message="uploadForm.errors.file || confirmForm.errors.file" class="mt-2" />
                </div>

                <!-- Addım 3: önizləmə -->
                <div v-if="rows" class="bg-white shadow-sm rounded-lg p-6">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <h3 class="text-lg font-semibold text-gray-900">3. Önizləmə</h3>
                        <div class="text-sm">
                            <span class="text-green-700">{{ validCount }} hazır</span>
                            <span v-if="invalidRows.length" class="ml-3 text-red-700">
                                {{ invalidRows.length }} xətalı
                            </span>
                        </div>
                    </div>

                    <div
                        v-if="invalidRows.length"
                        class="mt-3 rounded-md border border-red-200 bg-red-50 p-3 text-sm text-red-800"
                    >
                        Xətalı sətirlər var. Import bütöv aparılır: bir sətir belə xətalıdırsa
                        heç bir sual yazılmır. Faylı düzəldib yenidən yükləyin.
                    </div>

                    <div v-if="!rows.length" class="mt-3 text-sm text-gray-500">
                        Faylda sual tapılmadı.
                    </div>

                    <div v-else class="mt-4 overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-3 py-2 text-left font-medium text-gray-500">#</th>
                                    <th class="px-3 py-2 text-left font-medium text-gray-500">Sual</th>
                                    <th class="px-3 py-2 text-left font-medium text-gray-500">Tip</th>
                                    <th class="px-3 py-2 text-left font-medium text-gray-500">Düzgün cavab</th>
                                    <th class="px-3 py-2 text-left font-medium text-gray-500">Vəziyyət</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <tr v-for="row in rows" :key="row.number" :class="row.errors.length ? 'bg-red-50' : ''">
                                    <td class="px-3 py-2 align-top text-gray-500">{{ row.number }}</td>
                                    <td class="px-3 py-2 align-top max-w-md">
                                        <MathText :text="row.question_text || '—'" />
                                    </td>
                                    <td class="px-3 py-2 align-top whitespace-nowrap">
                                        {{ TYPE_LABELS[row.type] ?? '—' }}
                                    </td>
                                    <td class="px-3 py-2 align-top">
                                        <span v-if="row.type === 'multiple_choice'">
                                            {{ row.options.find((option) => option.is_correct)?.option_letter ?? '—' }}
                                        </span>
                                        <span v-else-if="row.type === 'open_coded'">
                                            {{ row.accepted_answers.join(' , ') }}
                                        </span>
                                        <span v-else class="text-gray-400">əl ilə yoxlanır</span>
                                    </td>
                                    <td class="px-3 py-2 align-top">
                                        <span v-if="!row.errors.length" class="text-green-700">Hazır</span>
                                        <ul v-else class="list-disc list-inside text-red-700">
                                            <li v-for="error in row.errors" :key="error">{{ error }}</li>
                                        </ul>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-6 flex justify-end gap-4 border-t border-gray-200 pt-4">
                        <Link :href="route('admin.exams.show', exam.id)" class="px-4 py-2 text-gray-700 hover:text-gray-900">
                            Ləğv et
                        </Link>
                        <PrimaryButton
                            :class="{ 'opacity-25': !canImport || confirmForm.processing }"
                            :disabled="!canImport || confirmForm.processing"
                            @click="confirmImport"
                        >
                            {{ validCount }} sualı import et
                        </PrimaryButton>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

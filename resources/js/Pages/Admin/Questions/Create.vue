<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import QuestionForm from '@/Components/Questions/QuestionForm.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    exam: { type: Object, required: true },
    topics: { type: Array, default: () => [] },
    sections: { type: Array, default: () => [] },
    sectionId: { type: Number, default: null },
    allowedTypes: { type: Array, default: () => ['multiple_choice', 'open_coded', 'open_written'] },
});

const form = useForm({
    section_id: props.sectionId,
    question_text: '',
    type: 'multiple_choice',
    question_image: null,
    question_image_alt: '',
    remove_image: false,
    options: [],
    accepted_answers: [''],
    explanation: '',
    topic_id: null,
    difficulty: 'medium',
    source: '',
});

const submit = () => {
    // Şəkil yüklənə bildiyi üçün həmişə FormData
    form.post(route('admin.exams.questions.store', props.exam.id), { forceFormData: true });
};
</script>

<template>
    <Head title="Sual əlavə et" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">
                    Sual əlavə et: {{ exam.title }}
                </h2>
                <Link :href="route('admin.exams.show', exam.id)" class="text-sm text-indigo-600 hover:text-indigo-800">
                    Geri
                </Link>
            </div>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-3xl sm:px-6 lg:px-8">
                <div v-if="sections.length > 1" class="mb-4 bg-white shadow-sm rounded-lg p-4">
                    <label for="section_id" class="block text-sm font-medium text-gray-700">Bölmə</label>
                    <select id="section_id" v-model="form.section_id"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm">
                        <option v-for="section in sections" :key="section.id" :value="section.id">
                            {{ section.title }}
                        </option>
                    </select>
                </div>

                <QuestionForm
                    :form="form"
                    :allowed-types="allowedTypes"
                    :exam="exam"
                    :topics="topics"
                    submit-label="Sual əlavə et"
                    :cancel-href="route('admin.exams.show', exam.id)"
                    @submit="submit"
                />
            </div>
        </div>
    </AuthenticatedLayout>
</template>

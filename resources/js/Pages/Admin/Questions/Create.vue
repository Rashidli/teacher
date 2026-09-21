<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import QuestionForm from '@/Components/Questions/QuestionForm.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    exam: { type: Object, required: true },
    topics: { type: Array, default: () => [] },
});

const form = useForm({
    question_text: '',
    type: 'multiple_choice',
    question_image: null,
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
                <QuestionForm
                    :form="form"
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

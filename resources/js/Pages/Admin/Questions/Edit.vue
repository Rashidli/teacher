<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import QuestionForm from '@/Components/Questions/QuestionForm.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';

const props = defineProps({
    exam: { type: Object, required: true },
    question: { type: Object, required: true },
    topics: { type: Array, default: () => [] },
    attemptUsage: { type: Number, default: 0 },
    allowedTypes: { type: Array, default: () => ['multiple_choice', 'open_coded', 'open_written'] },
});

const form = useForm({
    // PUT + fayl yükləməsi birlikdə işləmir: Laravel method spoofing istifadə olunur
    _method: 'put',
    question_text: props.question.question_text ?? '',
    type: props.question.type,
    question_image: null,
    question_image_alt: '',
    remove_image: false,
    options: (props.question.options ?? []).map((option) => ({
        option_letter: option.option_letter,
        option_text: option.option_text ?? '',
        option_image: null,
        is_correct: Boolean(option.is_correct),
    })),
    accepted_answers: props.question.accepted_answers?.length ? [...props.question.accepted_answers] : [''],
    explanation: props.question.explanation ?? '',
    grading_rubric: props.question.grading_rubric ?? '',
    topic_id: props.question.topic_id ?? null,
    difficulty: props.question.difficulty ?? 'medium',
    source: props.question.source ?? '',
});

// Cəhdlərdə işlənmiş sualı dəyişmək əvəzinə kopyasını yaradıb imtahanda əvəzləmək
const duplicate = () => {
    if (confirm('Sualın kopyası yaradılsın və imtahanda onunla əvəzlənsin? Köhnə nəticələr toxunulmayacaq.')) {
        router.post(route('admin.exams.questions.duplicate', [props.exam.id, props.question.id]));
    }
};

const existingImageUrl = props.question.question_image
    ? `/storage/${props.question.question_image}`
    : null;

const submit = () => {
    form.post(route('admin.exams.questions.update', [props.exam.id, props.question.id]), {
        forceFormData: true,
    });
};
</script>

<template>
    <Head title="Sualı redaktə et" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">
                    Sualı redaktə et: {{ exam.title }}
                </h2>
                <Link :href="route('admin.exams.show', exam.id)" class="text-sm text-indigo-600 hover:text-indigo-800">
                    Geri
                </Link>
            </div>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-3xl sm:px-6 lg:px-8">
                <div v-if="attemptUsage > 0" class="mb-4 flex justify-end">
                    <button type="button" @click="duplicate"
                        class="px-4 py-2 bg-white border border-indigo-300 text-indigo-700 text-sm rounded-lg hover:bg-indigo-50">
                        Kopyala və imtahanda əvəzlə
                    </button>
                </div>

                <QuestionForm
                    :form="form"
                    :allowed-types="allowedTypes"
                    :exam="exam"
                    :topics="topics"
                    :attempt-usage="attemptUsage"
                    :existing-image-url="existingImageUrl"
                    submit-label="Yadda saxla"
                    :cancel-href="route('admin.exams.show', exam.id)"
                    @submit="submit"
                />
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import MathText from '@/Components/MathText.vue';
import QuestionImage from '@/Components/QuestionImage.vue';
import { Head, Link, router } from '@inertiajs/vue3';

const props = defineProps({
    attempt: { type: Object, required: true },
    answers: { type: Array, default: () => [] },
    // Şkala config-dən gəlir: 0, 1/3, 1/2, 2/3, 1
    scale: { type: Array, default: () => [] },
});

const grade = (answer, value) => {
    router.post(
        route('admin.grading.update', [props.attempt.id, answer.id]),
        { grade_ratio: value },
        { preserveScroll: true },
    );
};

const isSelected = (answer, value) =>
    answer.grade_ratio !== null && Math.abs(answer.grade_ratio - value) < 0.001;
</script>

<template>
    <Head title="Cavabların qiymətləndirilməsi" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">
                    {{ attempt.student }} — {{ attempt.exam }}
                </h2>
                <Link :href="route('admin.grading.index')" class="text-sm text-indigo-600 hover:text-indigo-800">
                    Növbəyə qayıt
                </Link>
            </div>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-4xl sm:px-6 lg:px-8 space-y-6">
                <div class="bg-white shadow-sm rounded-lg p-6">
                    <dl class="grid grid-cols-3 gap-4 text-sm">
                        <div>
                            <dt class="text-gray-500">Fənn</dt>
                            <dd class="text-gray-900">{{ attempt.subject }}</dd>
                        </div>
                        <div>
                            <dt class="text-gray-500">Nisbi bal (100)</dt>
                            <dd class="text-gray-900">{{ attempt.relative_score }}</dd>
                        </div>
                        <div>
                            <dt class="text-gray-500">Fənn balı</dt>
                            <dd class="text-gray-900">{{ attempt.total_score }}</dd>
                        </div>
                    </dl>
                    <p class="mt-3 text-xs text-gray-500">
                        Hər qiymətdən sonra bal avtomatik yenidən hesablanır. Bütün yazılı cavablar
                        yoxlananda cəhd tamamlanmış sayılır.
                    </p>
                </div>

                <div v-for="(answer, index) in answers" :key="answer.id" class="bg-white shadow-sm rounded-lg p-6">
                    <div class="flex items-start gap-3">
                        <span class="px-2 py-1 bg-indigo-100 text-indigo-800 text-sm rounded">{{ index + 1 }}</span>
                        <div class="flex-1">
                            <MathText :text="answer.question_text" class="font-medium text-gray-900" />
                            <QuestionImage :path="answer.question_image" :alt="answer.question_image_alt" />
                        </div>
                        <span v-if="answer.grade_ratio !== null" class="text-xs text-green-700">Yoxlanıb</span>
                    </div>

                    <div class="mt-4">
                        <p class="text-xs font-medium text-gray-500">Şagirdin cavabı</p>
                        <div class="mt-1 rounded-md border border-gray-200 bg-gray-50 p-3 whitespace-pre-wrap text-sm text-gray-900">
                            {{ answer.open_answer || '— cavab yazılmayıb —' }}
                        </div>
                    </div>

                    <div v-if="answer.explanation" class="mt-3">
                        <p class="text-xs font-medium text-gray-500">Sualın izahı (yalnız sizə görünür)</p>
                        <p class="mt-1 text-sm text-gray-600">{{ answer.explanation }}</p>
                    </div>

                    <div class="mt-4 flex flex-wrap gap-2">
                        <button
                            v-for="step in scale"
                            :key="step.value"
                            type="button"
                            @click="grade(answer, step.value)"
                            :class="[
                                'px-4 py-2 rounded-lg border text-sm font-medium transition',
                                isSelected(answer, step.value)
                                    ? 'bg-green-600 text-white border-green-600'
                                    : 'bg-white text-gray-700 border-gray-300 hover:border-green-400',
                            ]"
                        >
                            {{ step.label }}
                        </button>
                    </div>
                </div>

                <p v-if="!answers.length" class="text-sm text-gray-500">
                    Bu cəhddə yazılı sual yoxdur.
                </p>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

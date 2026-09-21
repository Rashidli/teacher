<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import MathText from '@/Components/MathText.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    exam: Object,
});

const deleteQuestion = (question) => {
    if (confirm('Bu sualı silmək istədiyinizə əminsiniz?')) {
        useForm({}).delete(route('teacher.exams.questions.destroy', [props.exam.id, question.id]));
    }
};
</script>

<template>
    <Head :title="`İmtahan: ${exam.title}`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">
                    {{ exam.title }}
                </h2>
                <div class="flex gap-3">
                    <Link
                        :href="route('teacher.exams.edit', exam.id)"
                        class="px-4 py-2 bg-yellow-500 text-white rounded-lg hover:bg-yellow-600"
                    >
                        Redaktə Et
                    </Link>
                    <Link
                        :href="route('teacher.exams.index')"
                        class="text-sm text-indigo-600 hover:text-indigo-800 flex items-center"
                    >
                        Geri
                    </Link>
                </div>
            </div>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <!-- Exam Info -->
                <div class="bg-white overflow-hidden shadow-sm rounded-lg mb-6">
                    <div class="p-6">
                        <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                            <div>
                                <p class="text-sm font-medium text-gray-500">Fənn</p>
                                <p class="mt-1">
                                    <span class="px-2 py-1 bg-indigo-100 text-indigo-800 rounded text-sm">
                                        {{ exam.subject?.name }}
                                    </span>
                                </p>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-gray-500">Müddət</p>
                                <p class="mt-1 text-gray-900">{{ exam.duration_minutes }} dəqiqə</p>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-gray-500">Status</p>
                                <div class="mt-1 flex gap-2">
                                    <span
                                        :class="[
                                            'px-2 py-1 text-xs rounded-full',
                                            exam.is_published ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'
                                        ]"
                                    >
                                        {{ exam.is_published ? 'Dərc edilib' : 'Qaralama' }}
                                    </span>
                                    <span
                                        :class="[
                                            'px-2 py-1 text-xs rounded-full',
                                            exam.is_active ? 'bg-blue-100 text-blue-800' : 'bg-gray-100 text-gray-600'
                                        ]"
                                    >
                                        {{ exam.is_active ? 'Aktiv' : 'Deaktiv' }}
                                    </span>
                                </div>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-gray-500">Sual Sayı</p>
                                <p class="mt-1 text-gray-900">{{ exam.questions?.length || 0 }} sual</p>
                            </div>
                        </div>
                        <div v-if="exam.description" class="mt-4 pt-4 border-t border-gray-200">
                            <p class="text-sm font-medium text-gray-500">Təsvir</p>
                            <p class="mt-1 text-gray-900">{{ exam.description }}</p>
                        </div>
                    </div>
                </div>

                <!-- Questions -->
                <div class="bg-white overflow-hidden shadow-sm rounded-lg">
                    <div class="p-6 border-b border-gray-200">
                        <div class="flex items-center justify-between">
                            <h3 class="text-lg font-semibold text-gray-900">Suallar</h3>
                            <Link
                                :href="route('teacher.exams.questions.create', exam.id)"
                                class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700"
                            >
                                Sual Əlavə Et
                            </Link>
                        </div>
                    </div>

                    <div v-if="exam.questions?.length" class="divide-y divide-gray-200">
                        <div
                            v-for="(question, index) in exam.questions"
                            :key="question.id"
                            class="p-6"
                        >
                            <div class="flex items-start gap-4">
                                <span class="flex-shrink-0 w-8 h-8 bg-indigo-100 text-indigo-800 rounded-full flex items-center justify-center text-sm font-medium">
                                    {{ index + 1 }}
                                </span>
                                <div class="flex-1">
                                    <div class="flex items-start justify-between">
                                        <div>
                                            <p class="text-gray-900 font-medium"><MathText :text="question.question_text" /></p>
                                            <p class="text-sm text-gray-500 mt-1">
                                                {{ question.type === 'multiple_choice' ? 'Çoxseçimli' : 'Açıq cavab' }}
                                            </p>
                                        </div>
                                        <div class="flex gap-2">
                                            <Link
                                                :href="route('teacher.exams.questions.edit', [exam.id, question.id])"
                                                class="text-yellow-600 hover:text-yellow-800 text-sm"
                                            >
                                                Redaktə
                                            </Link>
                                            <button
                                                @click="deleteQuestion(question)"
                                                class="text-red-600 hover:text-red-800 text-sm"
                                            >
                                                Sil
                                            </button>
                                        </div>
                                    </div>

                                    <div v-if="question.image_path" class="mt-3">
                                        <img
                                            :src="`/storage/${question.image_path}`"
                                            alt="Sual şəkli"
                                            class="max-w-xs rounded-lg border border-gray-200"
                                        />
                                    </div>

                                    <div v-if="question.options?.length" class="mt-4 space-y-2">
                                        <div
                                            v-for="(option, optIndex) in question.options"
                                            :key="option.id"
                                            :class="[
                                                'flex items-center gap-3 p-3 rounded-lg',
                                                option.is_correct ? 'bg-green-50 border border-green-200' : 'bg-gray-50'
                                            ]"
                                        >
                                            <span class="w-6 h-6 bg-white rounded-full flex items-center justify-center text-sm font-medium border">
                                                {{ String.fromCharCode(65 + optIndex) }}
                                            </span>
                                            <span class="flex-1"><MathText :text="option.option_text" /></span>
                                            <span v-if="option.is_correct" class="text-xs text-green-600 font-medium">
                                                Düzgün
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div v-else class="p-12 text-center">
                        <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                            <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </div>
                        <p class="text-gray-500 mb-4">Bu imtahana hələ sual əlavə edilməyib</p>
                        <Link
                            :href="route('teacher.exams.questions.create', exam.id)"
                            class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700"
                        >
                            İlk Sualı Əlavə Et
                        </Link>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

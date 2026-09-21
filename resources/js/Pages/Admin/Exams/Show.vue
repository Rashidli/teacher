<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    exam: Object,
});

const togglePublish = () => {
    useForm({}).post(route('admin.exams.toggle-publish', props.exam.id));
};

const toggleActive = () => {
    useForm({}).post(route('admin.exams.toggle-active', props.exam.id));
};
</script>

<template>
    <Head :title="`İmtahan: ${exam.title}`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">
                    İmtahan Detalları
                </h2>
                <Link
                    :href="route('admin.exams.index')"
                    class="text-sm text-indigo-600 hover:text-indigo-800"
                >
                    Geri
                </Link>
            </div>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-sm rounded-lg">
                    <div class="p-6">
                        <!-- Exam Info -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                            <div>
                                <h3 class="text-lg font-semibold text-gray-900 mb-4">İmtahan Məlumatları</h3>
                                <dl class="space-y-3">
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500">Başlıq</dt>
                                        <dd class="text-sm text-gray-900">{{ exam.title }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500">Təsvir</dt>
                                        <dd class="text-sm text-gray-900">{{ exam.description || '-' }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500">Fənn</dt>
                                        <dd>
                                            <span class="px-2 py-1 text-xs bg-indigo-100 text-indigo-800 rounded">
                                                {{ exam.subject?.name }}
                                            </span>
                                        </dd>
                                    </div>
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500">Müddət</dt>
                                        <dd class="text-sm text-gray-900">{{ exam.duration_minutes }} dəqiqə</dd>
                                    </div>
                                </dl>
                            </div>

                            <div>
                                <h3 class="text-lg font-semibold text-gray-900 mb-4">Müəllim & Status</h3>
                                <dl class="space-y-3">
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500">Müəllim</dt>
                                        <dd class="text-sm text-gray-900">{{ exam.teacher?.full_name ?? '—' }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500">Sual Sayı</dt>
                                        <dd class="text-sm text-gray-900">{{ exam.questions?.length || 0 }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500">İştirakçı Sayı</dt>
                                        <dd class="text-sm text-gray-900">{{ exam.attempts_count || 0 }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500">Yaradılma Tarixi</dt>
                                        <dd class="text-sm text-gray-900">
                                            {{ new Date(exam.created_at).toLocaleDateString('az-AZ') }}
                                        </dd>
                                    </div>
                                </dl>
                            </div>
                        </div>

                        <!-- Actions -->
                        <div class="border-t border-gray-200 pt-6 mb-8">
                            <h3 class="text-lg font-semibold text-gray-900 mb-4">Əməliyyatlar</h3>
                            <div class="flex gap-4">
                                <button
                                    @click="togglePublish"
                                    :class="[
                                        'px-4 py-2 rounded-lg',
                                        exam.is_published
                                            ? 'bg-yellow-600 text-white hover:bg-yellow-700'
                                            : 'bg-green-600 text-white hover:bg-green-700'
                                    ]"
                                >
                                    {{ exam.is_published ? 'Dərci Ləğv Et' : 'Dərc Et' }}
                                </button>
                                <button
                                    @click="toggleActive"
                                    :class="[
                                        'px-4 py-2 rounded-lg',
                                        exam.is_active
                                            ? 'bg-red-600 text-white hover:bg-red-700'
                                            : 'bg-green-600 text-white hover:bg-green-700'
                                    ]"
                                >
                                    {{ exam.is_active ? 'Deaktiv Et' : 'Aktiv Et' }}
                                </button>
                            </div>
                        </div>

                        <!-- Questions -->
                        <div class="border-t border-gray-200 pt-6">
                            <h3 class="text-lg font-semibold text-gray-900 mb-4">Suallar</h3>
                            <div v-if="exam.questions?.length" class="space-y-4">
                                <div
                                    v-for="(question, index) in exam.questions"
                                    :key="question.id"
                                    class="p-4 bg-gray-50 rounded-lg"
                                >
                                    <div class="flex items-start gap-3">
                                        <span class="px-2 py-1 bg-indigo-100 text-indigo-800 text-sm rounded">
                                            {{ index + 1 }}
                                        </span>
                                        <div class="flex-1">
                                            <p class="text-gray-900 font-medium">{{ question.question_text }}</p>
                                            <div v-if="question.options?.length" class="mt-3 space-y-2">
                                                <div
                                                    v-for="option in question.options"
                                                    :key="option.id"
                                                    :class="[
                                                        'p-2 rounded text-sm',
                                                        option.is_correct
                                                            ? 'bg-green-100 text-green-800 border border-green-300'
                                                            : 'bg-white border border-gray-200'
                                                    ]"
                                                >
                                                    {{ option.option_text }}
                                                    <span v-if="option.is_correct" class="ml-2 text-xs">(Düzgün)</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <p v-else class="text-gray-500">Hələ sual əlavə edilməyib</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

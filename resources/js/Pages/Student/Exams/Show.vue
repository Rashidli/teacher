<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { useFeatures } from '@/Composables/useFeatures';

const props = defineProps({
    exam: Object,
    activeAttempt: Object,
    completedAttempts: Array,
    hasAccess: { type: Boolean, default: true },
    access: { type: Object, default: null },
    purchasesEnabled: { type: Boolean, default: false },
});

const form = useForm({});
const purchaseForm = useForm({});
const { teachersEnabled } = useFeatures();

const startExam = () => {
    form.post(route('student.exams.start', props.exam.id));
};

const purchase = () => {
    purchaseForm.post(route('student.exams.purchase', props.exam.id));
};
</script>

<template>
    <Head :title="exam.title" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">
                    İmtahan Haqqında
                </h2>
                <Link
                    :href="route('student.exams.index')"
                    class="text-sm text-indigo-600 hover:text-indigo-800"
                >
                    Geri
                </Link>
            </div>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-3xl sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-sm rounded-lg">
                    <div class="p-6">
                        <!-- Exam Info -->
                        <div class="text-center mb-8">
                            <div class="flex justify-center gap-2">
                                <span class="px-3 py-1 bg-indigo-100 text-indigo-800 rounded-full text-sm">
                                    {{ exam.subject?.name }}
                                </span>
                                <span class="px-3 py-1 bg-green-100 text-green-800 rounded-full text-sm">
                                    {{ exam.group?.name }}
                                </span>
                            </div>
                            <h1 class="text-2xl font-bold text-gray-900 mt-4">{{ exam.title }}</h1>
                            <p v-if="exam.description" class="text-gray-500 mt-2">{{ exam.description }}</p>
                        </div>

                        <!-- Stats -->
                        <div class="grid grid-cols-3 gap-4 mb-8">
                            <div class="text-center p-4 bg-gray-50 rounded-lg">
                                <p class="text-2xl font-bold text-gray-900">{{ exam.questions_count }}</p>
                                <p class="text-sm text-gray-500">Sual</p>
                            </div>
                            <div class="text-center p-4 bg-gray-50 rounded-lg">
                                <p class="text-2xl font-bold text-gray-900">{{ exam.duration_minutes }}</p>
                                <p class="text-sm text-gray-500">Dəqiqə</p>
                            </div>
                            <div class="text-center p-4 bg-gray-50 rounded-lg">
                                <p class="text-2xl font-bold text-gray-900">{{ exam.attempts_count || 0 }}</p>
                                <p class="text-sm text-gray-500">İştirakçı</p>
                            </div>
                        </div>

                        <!-- Teacher Info: müəllim modulu aktiv olanda və müəllim təyin olunubsa -->
                        <div v-if="teachersEnabled && exam.teacher" class="flex items-center justify-center mb-8 text-gray-500">
                            <span>Müəllim: {{ exam.teacher?.full_name }}</span>
                        </div>

                        <!-- Active Attempt -->
                        <div v-if="activeAttempt" class="mb-8 p-4 bg-orange-50 border border-orange-200 rounded-lg">
                            <h3 class="font-semibold text-orange-900 mb-2">Aktiv İmtahanınız Var</h3>
                            <p class="text-sm text-orange-700 mb-3">Bu imtahanda yarımçıq qalmış cəhdiniz var.</p>
                            <Link
                                :href="route('student.exams.attempt', activeAttempt.id)"
                                class="inline-block px-4 py-2 bg-orange-600 text-white rounded-lg hover:bg-orange-700"
                            >
                                Davam Et
                            </Link>
                        </div>

                        <!-- Completed Attempts -->
                        <div v-if="completedAttempts?.length" class="mb-8">
                            <h3 class="font-semibold text-gray-900 mb-3">Əvvəlki Nəticələriniz</h3>
                            <div class="space-y-3">
                                <div
                                    v-for="attempt in completedAttempts"
                                    :key="attempt.id"
                                    class="p-4 bg-blue-50 border border-blue-200 rounded-lg"
                                >
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <p class="text-sm text-blue-700">
                                                {{ new Date(attempt.finished_at).toLocaleDateString('az-AZ') }}
                                            </p>
                                            <p class="text-sm text-blue-700">
                                                {{ attempt.correct_answers }}/{{ exam.questions_count }} düzgün
                                            </p>
                                        </div>
                                        <div class="text-right">
                                            <p class="text-2xl font-bold text-blue-900">{{ attempt.total_score }}</p>
                                            <p class="text-sm text-blue-700">bal</p>
                                        </div>
                                    </div>
                                    <Link
                                        :href="route('student.exams.result', attempt.id)"
                                        class="mt-2 inline-block text-sm text-blue-600 hover:text-blue-800"
                                    >
                                        Ətraflı bax →
                                    </Link>
                                </div>
                            </div>
                        </div>

                        <!-- Alış: giriş yoxdursa imtahan başladıla bilmir -->
                        <div v-if="!hasAccess" class="border-t border-gray-200 pt-6">
                            <div class="rounded-lg border border-indigo-200 bg-indigo-50 p-4">
                                <div class="flex flex-wrap items-baseline justify-between gap-2">
                                    <h4 class="font-medium text-indigo-900">Bu imtahan ödənişlidir</h4>
                                    <span class="text-2xl font-semibold text-indigo-900">{{ exam.price }} AZN</span>
                                </div>

                                <button
                                    v-if="purchasesEnabled"
                                    type="button"
                                    @click="purchase"
                                    :disabled="purchaseForm.processing"
                                    class="mt-4 w-full py-3 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 disabled:opacity-50 font-semibold"
                                >
                                    {{ purchaseForm.processing ? 'Yönləndirilir...' : 'Al' }}
                                </button>

                                <p v-else class="mt-4 text-sm text-indigo-900">
                                    Onlayn ödəniş hazırda aktiv deyil. İmtahanı almaq üçün bizimlə əlaqə saxlayın —
                                    köçürmə təsdiqləndikdən sonra imtahan hesabınızda açılacaq.
                                </p>
                            </div>
                        </div>

                        <!-- Start Exam -->
                        <div v-else-if="!activeAttempt" class="border-t border-gray-200 pt-6">
                            <form @submit.prevent="startExam" class="space-y-6">
                                <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                                    <h4 class="font-medium text-yellow-800 mb-2">Diqqət!</h4>
                                    <ul class="text-sm text-yellow-700 space-y-1">
                                        <li>İmtahana başladıqdan sonra {{ exam.duration_minutes }} dəqiqə vaxtınız olacaq</li>
                                        <li>Vaxt bitdikdə imtahan avtomatik bitiriləcək</li>
                                        <li>Suallar arasında sərbəst keçid edə bilərsiniz</li>
                                        <li>Cavablarınız avtomatik yadda saxlanılır</li>
                                    </ul>
                                </div>

                                <button
                                    type="submit"
                                    :disabled="form.processing"
                                    class="w-full py-3 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 disabled:opacity-50 font-semibold"
                                >
                                    {{ form.processing ? 'Yüklənir...' : 'İmtahana Başla' }}
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

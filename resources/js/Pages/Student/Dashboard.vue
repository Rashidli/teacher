<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    stats: Object,
    recentAttempts: Array,
    availableExams: Array,
});
</script>

<template>
    <Head title="Şagird Paneli" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                Şagird Paneli
            </h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <!-- Stats Cards -->
                <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4 mb-8">
                    <div class="bg-white overflow-hidden shadow-sm rounded-lg p-6">
                        <div class="text-sm font-medium text-gray-500">Verilən İmtahanlar</div>
                        <div class="mt-2 text-3xl font-semibold text-gray-900">{{ stats?.totalAttempts || 0 }}</div>
                    </div>
                    <div class="bg-white overflow-hidden shadow-sm rounded-lg p-6">
                        <div class="text-sm font-medium text-gray-500">Orta Bal</div>
                        <div class="mt-2 text-3xl font-semibold text-indigo-600">{{ stats?.averageScore || 0 }}</div>
                    </div>
                    <div class="bg-white overflow-hidden shadow-sm rounded-lg p-6">
                        <div class="text-sm font-medium text-gray-500">Ən Yüksək Bal</div>
                        <div class="mt-2 text-3xl font-semibold text-green-600">{{ stats?.highestScore || 0 }}</div>
                    </div>
                    <div class="bg-white overflow-hidden shadow-sm rounded-lg p-6">
                        <div class="text-sm font-medium text-gray-500">Düzgün Cavab %</div>
                        <div class="mt-2 text-3xl font-semibold text-gray-900">{{ stats?.correctPercentage || 0 }}%</div>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                    <!-- Available Exams -->
                    <div class="bg-white overflow-hidden shadow-sm rounded-lg">
                        <div class="p-6 border-b border-gray-200">
                            <div class="flex justify-between items-center">
                                <h3 class="text-lg font-semibold text-gray-900">Mövcud İmtahanlar</h3>
                                <Link
                                    :href="route('student.exams.index')"
                                    class="text-sm text-indigo-600 hover:text-indigo-800"
                                >
                                    Hamısına bax
                                </Link>
                            </div>
                        </div>
                        <div class="p-6">
                            <div v-if="availableExams?.length" class="space-y-4">
                                <div
                                    v-for="exam in availableExams"
                                    :key="exam.id"
                                    class="flex items-center justify-between p-3 bg-gray-50 rounded-lg"
                                >
                                    <div>
                                        <p class="font-medium text-gray-900">{{ exam.title }}</p>
                                        <p class="text-sm text-gray-500">
                                            {{ exam.subject?.name }} - {{ exam.duration_minutes }} dəq
                                        </p>
                                    </div>
                                    <Link
                                        :href="route('student.exams.show', exam.id)"
                                        class="px-3 py-1 text-sm bg-indigo-600 text-white rounded hover:bg-indigo-700"
                                    >
                                        Başla
                                    </Link>
                                </div>
                            </div>
                            <p v-else class="text-gray-500 text-center py-4">
                                Hazırda mövcud imtahan yoxdur
                            </p>
                        </div>
                    </div>

                    <!-- Recent Attempts -->
                    <div class="bg-white overflow-hidden shadow-sm rounded-lg">
                        <div class="p-6 border-b border-gray-200">
                            <div class="flex justify-between items-center">
                                <h3 class="text-lg font-semibold text-gray-900">Son Nəticələr</h3>
                                <Link
                                    :href="route('student.results.index')"
                                    class="text-sm text-indigo-600 hover:text-indigo-800"
                                >
                                    Hamısına bax
                                </Link>
                            </div>
                        </div>
                        <div class="p-6">
                            <div v-if="recentAttempts?.length" class="space-y-4">
                                <div
                                    v-for="attempt in recentAttempts"
                                    :key="attempt.id"
                                    class="flex items-center justify-between p-3 bg-gray-50 rounded-lg"
                                >
                                    <div>
                                        <p class="font-medium text-gray-900">{{ attempt.exam?.title }}</p>
                                        <p class="text-sm text-gray-500">
                                            {{ new Date(attempt.finished_at).toLocaleDateString('az-AZ') }}
                                        </p>
                                    </div>
                                    <div class="text-right">
                                        <p class="font-semibold text-indigo-600">{{ attempt.score }} bal</p>
                                        <p class="text-xs text-gray-500">
                                            {{ attempt.correct_answers }}/{{ attempt.total_questions }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                            <p v-else class="text-gray-500 text-center py-4">
                                Hələ imtahan verməmisiniz
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

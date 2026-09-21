<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    stats: Object,
    recentExams: Array,
});
</script>

<template>
    <Head title="Müəllim Paneli" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                Müəllim Paneli
            </h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <!-- Stats Cards -->
                <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4 mb-8">
                    <div class="bg-white overflow-hidden shadow-sm rounded-lg p-6">
                        <div class="text-sm font-medium text-gray-500">İmtahanlarım</div>
                        <div class="mt-2 text-3xl font-semibold text-gray-900">{{ stats?.totalExams || 0 }}</div>
                    </div>
                    <div class="bg-white overflow-hidden shadow-sm rounded-lg p-6">
                        <div class="text-sm font-medium text-gray-500">Dərc Edilmiş</div>
                        <div class="mt-2 text-3xl font-semibold text-green-600">{{ stats?.publishedExams || 0 }}</div>
                    </div>
                    <div class="bg-white overflow-hidden shadow-sm rounded-lg p-6">
                        <div class="text-sm font-medium text-gray-500">Ümumi Suallar</div>
                        <div class="mt-2 text-3xl font-semibold text-gray-900">{{ stats?.totalQuestions || 0 }}</div>
                    </div>
                    <div class="bg-white overflow-hidden shadow-sm rounded-lg p-6">
                        <div class="text-sm font-medium text-gray-500">İştirakçılar</div>
                        <div class="mt-2 text-3xl font-semibold text-gray-900">{{ stats?.totalAttempts || 0 }}</div>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                    <!-- Quick Actions -->
                    <div class="bg-white overflow-hidden shadow-sm rounded-lg">
                        <div class="p-6 border-b border-gray-200">
                            <h3 class="text-lg font-semibold text-gray-900">Sürətli Əməliyyatlar</h3>
                        </div>
                        <div class="p-6">
                            <div class="space-y-3">
                                <Link
                                    :href="route('teacher.exams.create')"
                                    class="flex items-center p-4 bg-indigo-50 rounded-lg hover:bg-indigo-100 transition"
                                >
                                    <div class="w-10 h-10 bg-indigo-600 rounded-lg flex items-center justify-center mr-4">
                                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                                        </svg>
                                    </div>
                                    <div>
                                        <p class="font-medium text-gray-900">Yeni İmtahan Yarat</p>
                                        <p class="text-sm text-gray-500">Yeni bir imtahan əlavə edin</p>
                                    </div>
                                </Link>
                                <Link
                                    :href="route('teacher.exams.index')"
                                    class="flex items-center p-4 bg-gray-50 rounded-lg hover:bg-gray-100 transition"
                                >
                                    <div class="w-10 h-10 bg-gray-600 rounded-lg flex items-center justify-center mr-4">
                                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                                        </svg>
                                    </div>
                                    <div>
                                        <p class="font-medium text-gray-900">İmtahanlarım</p>
                                        <p class="text-sm text-gray-500">Bütün imtahanları görüntüləyin</p>
                                    </div>
                                </Link>
                            </div>
                        </div>
                    </div>

                    <!-- Recent Exams -->
                    <div class="bg-white overflow-hidden shadow-sm rounded-lg">
                        <div class="p-6 border-b border-gray-200">
                            <div class="flex justify-between items-center">
                                <h3 class="text-lg font-semibold text-gray-900">Son İmtahanlar</h3>
                                <Link
                                    :href="route('teacher.exams.index')"
                                    class="text-sm text-indigo-600 hover:text-indigo-800"
                                >
                                    Hamısına bax
                                </Link>
                            </div>
                        </div>
                        <div class="p-6">
                            <div v-if="recentExams?.length" class="space-y-4">
                                <div
                                    v-for="exam in recentExams"
                                    :key="exam.id"
                                    class="flex items-center justify-between p-3 bg-gray-50 rounded-lg"
                                >
                                    <div>
                                        <p class="font-medium text-gray-900">{{ exam.title }}</p>
                                        <p class="text-sm text-gray-500">
                                            {{ exam.subject?.name }} - {{ exam.questions_count }} sual
                                        </p>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <span
                                            :class="[
                                                'px-2 py-1 text-xs rounded-full',
                                                exam.is_published ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'
                                            ]"
                                        >
                                            {{ exam.is_published ? 'Dərc edilib' : 'Qaralama' }}
                                        </span>
                                        <Link
                                            :href="route('teacher.exams.show', exam.id)"
                                            class="text-indigo-600 hover:text-indigo-800 text-sm"
                                        >
                                            Bax
                                        </Link>
                                    </div>
                                </div>
                            </div>
                            <p v-else class="text-gray-500 text-center py-4">
                                Hələ imtahan yaratmamısınız
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

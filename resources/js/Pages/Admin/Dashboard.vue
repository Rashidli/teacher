<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { useFeatures } from '@/Composables/useFeatures';

defineProps({
    stats: Object,
    pendingTeachers: Array,
    recentExams: Array,
});

const { teachersEnabled } = useFeatures();
</script>

<template>
    <Head title="Admin Panel" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                Admin Panel
            </h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <!-- Stats Cards -->
                <div :class="['grid grid-cols-1 gap-6 sm:grid-cols-2 mb-8', teachersEnabled ? 'lg:grid-cols-4' : '']">
                    <div v-if="teachersEnabled" class="bg-white overflow-hidden shadow-sm rounded-lg p-6">
                        <div class="text-sm font-medium text-gray-500">Ümumi Müəllimlər</div>
                        <div class="mt-2 text-3xl font-semibold text-gray-900">{{ stats?.teachers || 0 }}</div>
                    </div>
                    <div v-if="teachersEnabled" class="bg-white overflow-hidden shadow-sm rounded-lg p-6">
                        <div class="text-sm font-medium text-gray-500">Təsdiqlənməmiş Müəllimlər</div>
                        <div class="mt-2 text-3xl font-semibold text-yellow-600">{{ stats?.pendingTeachers || 0 }}</div>
                    </div>
                    <div class="bg-white overflow-hidden shadow-sm rounded-lg p-6">
                        <div class="text-sm font-medium text-gray-500">Ümumi Şagirdlər</div>
                        <div class="mt-2 text-3xl font-semibold text-gray-900">{{ stats?.students || 0 }}</div>
                    </div>
                    <div class="bg-white overflow-hidden shadow-sm rounded-lg p-6">
                        <div class="text-sm font-medium text-gray-500">Ümumi İmtahanlar</div>
                        <div class="mt-2 text-3xl font-semibold text-gray-900">{{ stats?.exams || 0 }}</div>
                    </div>
                </div>

                <div :class="['grid grid-cols-1 gap-8', teachersEnabled ? 'lg:grid-cols-2' : '']">
                    <!-- Pending Teachers (müəllim modulu aktiv olanda) -->
                    <div v-if="teachersEnabled" class="bg-white overflow-hidden shadow-sm rounded-lg">
                        <div class="p-6 border-b border-gray-200">
                            <div class="flex justify-between items-center">
                                <h3 class="text-lg font-semibold text-gray-900">Təsdiqlənməmiş Müəllimlər</h3>
                                <Link
                                    :href="route('admin.teachers.index')"
                                    class="text-sm text-indigo-600 hover:text-indigo-800"
                                >
                                    Hamısına bax
                                </Link>
                            </div>
                        </div>
                        <div class="p-6">
                            <div v-if="pendingTeachers?.length" class="space-y-4">
                                <div
                                    v-for="teacher in pendingTeachers"
                                    :key="teacher.id"
                                    class="flex items-center justify-between p-3 bg-gray-50 rounded-lg"
                                >
                                    <div>
                                        <p class="font-medium text-gray-900">{{ teacher.full_name }}</p>
                                        <p class="text-sm text-gray-500">{{ teacher.email }}</p>
                                    </div>
                                    <Link
                                        :href="route('admin.teachers.show', teacher.id)"
                                        class="px-3 py-1 text-sm bg-indigo-600 text-white rounded hover:bg-indigo-700"
                                    >
                                        Bax
                                    </Link>
                                </div>
                            </div>
                            <p v-else class="text-gray-500 text-center py-4">
                                Təsdiqlənməmiş müəllim yoxdur
                            </p>
                        </div>
                    </div>

                    <!-- Recent Exams -->
                    <div class="bg-white overflow-hidden shadow-sm rounded-lg">
                        <div class="p-6 border-b border-gray-200">
                            <div class="flex justify-between items-center">
                                <h3 class="text-lg font-semibold text-gray-900">Son İmtahanlar</h3>
                                <Link
                                    :href="route('admin.exams.index')"
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
                                            {{ exam.subject?.name }}<template v-if="teachersEnabled && exam.teacher"> - {{ exam.teacher.full_name }}</template>
                                        </p>
                                    </div>
                                    <span
                                        :class="[
                                            'px-2 py-1 text-xs rounded-full',
                                            exam.is_published ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'
                                        ]"
                                    >
                                        {{ exam.is_published ? 'Dərc edilib' : 'Gözləyir' }}
                                    </span>
                                </div>
                            </div>
                            <p v-else class="text-gray-500 text-center py-4">
                                Hələ imtahan yoxdur
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

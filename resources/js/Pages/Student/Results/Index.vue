<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { useFeatures } from '@/Composables/useFeatures';

defineProps({
    attempts: Object,
});

const { teachersEnabled } = useFeatures();

const getScoreColor = (attempt) => {
    const percentage = (attempt.correct_answers / attempt.total_questions) * 100;
    if (percentage >= 80) return 'text-green-600';
    if (percentage >= 60) return 'text-yellow-600';
    return 'text-red-600';
};
</script>

<template>
    <Head title="Nəticələrim" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                Nəticələrim
            </h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-sm rounded-lg">
                    <div v-if="attempts.data?.length" class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        İmtahan
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Fənn
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Qrup
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Nəticə
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Bal
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Tarix
                                    </th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Əməliyyat
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <tr v-for="attempt in attempts.data" :key="attempt.id">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium text-gray-900">
                                            {{ attempt.exam?.title }}
                                        </div>
                                        <div v-if="teachersEnabled && attempt.exam?.teacher" class="text-xs text-gray-500">
                                            {{ attempt.exam.teacher.full_name }}
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-2 py-1 text-xs bg-indigo-100 text-indigo-800 rounded">
                                            {{ attempt.exam?.subject?.name }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        {{ attempt.group?.name }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center gap-2">
                                            <span class="text-sm text-green-600 font-medium">
                                                {{ attempt.correct_answers }}
                                            </span>
                                            <span class="text-gray-400">/</span>
                                            <span class="text-sm text-red-600 font-medium">
                                                {{ attempt.wrong_answers }}
                                            </span>
                                            <span class="text-gray-400">/</span>
                                            <span class="text-sm text-gray-500">
                                                {{ attempt.total_questions }}
                                            </span>
                                        </div>
                                        <div class="text-xs text-gray-500">
                                            düzgün / səhv / ümumi
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span :class="['text-lg font-bold', getScoreColor(attempt)]">
                                            {{ attempt.score }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        {{ new Date(attempt.finished_at).toLocaleDateString('az-AZ') }}
                                        <br>
                                        <span class="text-xs">
                                            {{ new Date(attempt.finished_at).toLocaleTimeString('az-AZ', { hour: '2-digit', minute: '2-digit' }) }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                        <Link
                                            :href="route('student.exams.result', attempt.id)"
                                            class="text-indigo-600 hover:text-indigo-900"
                                        >
                                            Ətraflı
                                        </Link>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div v-else class="p-12 text-center">
                        <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                            <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                            </svg>
                        </div>
                        <p class="text-gray-500 mb-4">Hələ heç bir imtahan verməmisiniz</p>
                        <Link
                            :href="route('student.exams.index')"
                            class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700"
                        >
                            İmtahanlara Bax
                        </Link>
                    </div>

                    <!-- Pagination -->
                    <div v-if="attempts.links && attempts.data?.length" class="px-6 py-4 border-t border-gray-200">
                        <div class="flex justify-between items-center">
                            <div class="text-sm text-gray-500">
                                {{ attempts.from }} - {{ attempts.to }} / {{ attempts.total }}
                            </div>
                            <div class="flex gap-2">
                                <Link
                                    v-for="link in attempts.links"
                                    :key="link.label"
                                    :href="link.url"
                                    :class="[
                                        'px-3 py-1 text-sm rounded',
                                        link.active ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200',
                                        !link.url && 'opacity-50 cursor-not-allowed'
                                    ]"
                                    v-html="link.label"
                                />
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

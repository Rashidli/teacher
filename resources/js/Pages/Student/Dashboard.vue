<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { useLocale } from '@/Composables/useLocale';

// Yeni imtahan axtarışı kataloqdadır (kateqoriya ağacı) — burada tövsiyə bloku yoxdur.
// Onun yerini P3-dəki "Məqsədim" funksiyası tutacaq.
defineProps({
    stats: Object,
    inProgress: { type: Array, default: () => [] },
    recentResults: { type: Array, default: () => [] },
});

const { lroute } = useLocale();
</script>

<template>
    <Head title="Şagird Paneli" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">Şagird Paneli</h2>
                <Link
                    :href="lroute('exams.catalog')"
                    class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700"
                >
                    Kataloqa keç
                </Link>
            </div>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <!-- Stats Cards -->
                <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4 mb-8">
                    <div class="bg-white overflow-hidden shadow-sm rounded-lg p-6">
                        <div class="text-sm font-medium text-gray-500">Verilən imtahanlar</div>
                        <div class="mt-2 text-3xl font-semibold text-gray-900">{{ stats?.totalAttempts || 0 }}</div>
                    </div>
                    <div class="bg-white overflow-hidden shadow-sm rounded-lg p-6">
                        <div class="text-sm font-medium text-gray-500">Orta nəticə (100-lük)</div>
                        <div class="mt-2 text-3xl font-semibold text-indigo-600">{{ stats?.averageScore || 0 }}</div>
                    </div>
                    <div class="bg-white overflow-hidden shadow-sm rounded-lg p-6">
                        <div class="text-sm font-medium text-gray-500">Ən yüksək (100-lük)</div>
                        <div class="mt-2 text-3xl font-semibold text-green-600">{{ stats?.highestScore || 0 }}</div>
                    </div>
                    <div class="bg-white overflow-hidden shadow-sm rounded-lg p-6">
                        <div class="text-sm font-medium text-gray-500">Düzgün cavab %</div>
                        <div class="mt-2 text-3xl font-semibold text-gray-900">{{ stats?.correctPercentage || 0 }}%</div>
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-8 lg:grid-cols-2">
                    <!-- Davam edən cəhdlər -->
                    <div class="overflow-hidden rounded-lg bg-white shadow-sm">
                        <div class="border-b border-gray-200 p-6">
                            <h3 class="text-lg font-semibold text-gray-900">Davam edən imtahanlar</h3>
                        </div>
                        <div class="p-6">
                            <div v-if="inProgress.length" class="space-y-4">
                                <div
                                    v-for="item in inProgress"
                                    :key="item.id"
                                    class="flex flex-wrap items-center justify-between gap-3 rounded-lg bg-gray-50 p-3"
                                >
                                    <div>
                                        <p class="font-medium text-gray-900">{{ item.title }}</p>
                                        <p class="text-sm text-amber-700">{{ item.remaining_minutes }} dəqiqə qalıb</p>
                                    </div>
                                    <Link
                                        :href="item.url"
                                        class="rounded bg-amber-500 px-3 py-1 text-sm font-semibold text-white hover:bg-amber-600"
                                    >
                                        Davam et
                                    </Link>
                                </div>
                            </div>
                            <p v-else class="py-4 text-center text-gray-500">
                                Davam edən imtahanın yoxdur
                            </p>
                        </div>
                    </div>

                    <!-- Son nəticələr -->
                    <div class="overflow-hidden rounded-lg bg-white shadow-sm">
                        <div class="border-b border-gray-200 p-6">
                            <div class="flex items-center justify-between">
                                <h3 class="text-lg font-semibold text-gray-900">Son nəticələr</h3>
                                <Link
                                    :href="route('student.results.index')"
                                    class="text-sm text-indigo-600 hover:text-indigo-800"
                                >
                                    Hamısına bax
                                </Link>
                            </div>
                        </div>
                        <div class="p-6">
                            <div v-if="recentResults.length" class="space-y-4">
                                <Link
                                    v-for="item in recentResults"
                                    :key="item.id"
                                    :href="item.url"
                                    class="flex flex-wrap items-center justify-between gap-3 rounded-lg bg-gray-50 p-3 hover:bg-gray-100"
                                >
                                    <div>
                                        <p class="font-medium text-gray-900">{{ item.title }}</p>
                                        <p class="text-sm text-gray-500">{{ item.finished_at }}</p>
                                    </div>
                                    <div class="text-right">
                                        <p class="font-semibold text-indigo-600">{{ item.relative_score ?? 0 }} / 100</p>
                                        <p class="text-xs text-gray-500">
                                            {{ item.correct_answers }}/{{ item.question_count }} düz
                                        </p>
                                    </div>
                                </Link>
                            </div>
                            <p v-else class="py-4 text-center text-gray-500">
                                Hələ imtahan verməmisiniz
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

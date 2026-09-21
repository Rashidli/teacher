<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    attempts: { type: Object, required: true },
});
</script>

<template>
    <Head title="Qiymətləndirmə növbəsi" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                Qiymətləndirmə növbəsi
            </h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-5xl sm:px-6 lg:px-8">
                <div class="bg-white shadow-sm rounded-lg p-6">
                    <p class="text-sm text-gray-600">
                        Yazılı (açıq) suallar avtomatik yoxlanmır. Aşağıdakı cəhdlərin balı
                        siz qiymət verənə qədər müvəqqətidir.
                    </p>

                    <p v-if="!attempts.data?.length" class="mt-4 text-sm text-gray-500">
                        Yoxlanmalı cəhd yoxdur.
                    </p>

                    <div v-else class="mt-4 overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-3 py-2 text-left font-medium text-gray-500">Şagird</th>
                                    <th class="px-3 py-2 text-left font-medium text-gray-500">İmtahan</th>
                                    <th class="px-3 py-2 text-left font-medium text-gray-500">Yoxlanmamış</th>
                                    <th class="px-3 py-2 text-left font-medium text-gray-500">Bitirilib</th>
                                    <th class="px-3 py-2"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <tr v-for="attempt in attempts.data" :key="attempt.id">
                                    <td class="px-3 py-2">
                                        <div class="font-medium text-gray-900">{{ attempt.user?.full_name }}</div>
                                        <div class="text-xs text-gray-500">{{ attempt.user?.email }}</div>
                                    </td>
                                    <td class="px-3 py-2">
                                        {{ attempt.exam?.title }}
                                        <div class="text-xs text-gray-500">{{ attempt.exam?.subject?.name }}</div>
                                    </td>
                                    <td class="px-3 py-2">{{ attempt.ungraded_count }} sual</td>
                                    <td class="px-3 py-2 whitespace-nowrap text-gray-500">{{ attempt.finished_at }}</td>
                                    <td class="px-3 py-2 text-right">
                                        <Link :href="route('admin.grading.show', attempt.id)"
                                            class="px-3 py-1.5 bg-indigo-600 text-white text-sm rounded-lg hover:bg-indigo-700">
                                            Yoxla
                                        </Link>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

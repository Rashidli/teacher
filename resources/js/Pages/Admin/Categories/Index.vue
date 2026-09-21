<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';

defineProps({
    categories: { type: Array, default: () => [] },
});

const remove = (category) => {
    if (confirm(`"${category.name}" silinsin?`)) {
        router.delete(route('admin.categories.destroy', category.id), { preserveScroll: true });
    }
};
</script>

<template>
    <Head title="Kateqoriyalar" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">Kateqoriyalar</h2>
                <Link :href="route('admin.categories.create')"
                    class="px-4 py-2 bg-indigo-600 text-white text-sm rounded-lg hover:bg-indigo-700">
                    + Yeni kateqoriya
                </Link>
            </div>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-5xl sm:px-6 lg:px-8">
                <div class="bg-white shadow-sm rounded-lg overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-2 text-left font-medium text-gray-500">Ad</th>
                                <th class="px-4 py-2 text-left font-medium text-gray-500">URL</th>
                                <th class="px-4 py-2 text-left font-medium text-gray-500">İmtahan</th>
                                <th class="px-4 py-2 text-left font-medium text-gray-500">Vəziyyət</th>
                                <th class="px-4 py-2"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="category in categories" :key="category.id" :class="category.is_active ? '' : 'bg-gray-50 text-gray-400'">
                                <td class="px-4 py-2">
                                    <span :style="{ paddingLeft: `${category.depth * 20}px` }">
                                        <span v-if="category.depth" class="text-gray-400">└ </span>{{ category.name }}
                                    </span>
                                </td>
                                <td class="px-4 py-2 font-mono text-xs text-gray-500">/{{ category.path }}</td>
                                <td class="px-4 py-2">
                                    <span v-if="!category.has_exams" class="text-gray-400">yalnız məlumat</span>
                                    <span v-else>{{ category.exams_count }}</span>
                                </td>
                                <td class="px-4 py-2">
                                    <span v-if="category.is_active" class="text-green-700">Aktiv</span>
                                    <span v-else class="text-gray-500">Deaktiv</span>
                                </td>
                                <td class="px-4 py-2 text-right whitespace-nowrap">
                                    <Link :href="route('admin.categories.edit', category.id)"
                                        class="text-indigo-600 hover:text-indigo-800">Redaktə</Link>
                                    <button v-if="!category.children_count && !category.exams_count" type="button"
                                        @click="remove(category)" class="ml-3 text-red-600 hover:text-red-800">Sil</button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

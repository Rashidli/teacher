<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

defineProps({
    exams: Object,
});

const deleteExam = (exam) => {
    if (confirm('Bu imtahanı silmək istədiyinizə əminsiniz?')) {
        useForm({}).delete(route('teacher.exams.destroy', exam.id));
    }
};
</script>

<template>
    <Head title="İmtahanlarım" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">
                    İmtahanlarım
                </h2>
                <Link
                    :href="route('teacher.exams.create')"
                    class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700"
                >
                    Yeni İmtahan
                </Link>
            </div>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-sm rounded-lg">
                    <div v-if="exams.data?.length" class="overflow-x-auto">
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
                                        Suallar
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Müddət
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Status
                                    </th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Əməliyyatlar
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <tr v-for="exam in exams.data" :key="exam.id">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium text-gray-900">{{ exam.title }}</div>
                                        <div class="text-xs text-gray-500">
                                            {{ new Date(exam.created_at).toLocaleDateString('az-AZ') }}
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-2 py-1 text-xs bg-indigo-100 text-indigo-800 rounded">
                                            {{ exam.subject?.name }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        {{ exam.questions_count || 0 }} sual
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        {{ exam.duration_minutes }} dəq
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex flex-col gap-1">
                                            <span
                                                :class="[
                                                    'px-2 py-0.5 text-xs rounded-full inline-block w-fit',
                                                    exam.is_published
                                                        ? 'bg-green-100 text-green-800'
                                                        : 'bg-yellow-100 text-yellow-800'
                                                ]"
                                            >
                                                {{ exam.is_published ? 'Dərc edilib' : 'Qaralama' }}
                                            </span>
                                            <span
                                                :class="[
                                                    'px-2 py-0.5 text-xs rounded-full inline-block w-fit',
                                                    exam.is_active
                                                        ? 'bg-blue-100 text-blue-800'
                                                        : 'bg-gray-100 text-gray-600'
                                                ]"
                                            >
                                                {{ exam.is_active ? 'Aktiv' : 'Deaktiv' }}
                                            </span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                        <div class="flex justify-end gap-3">
                                            <Link
                                                :href="route('teacher.exams.show', exam.id)"
                                                class="text-indigo-600 hover:text-indigo-900"
                                            >
                                                Bax
                                            </Link>
                                            <Link
                                                :href="route('teacher.exams.edit', exam.id)"
                                                class="text-yellow-600 hover:text-yellow-900"
                                            >
                                                Redaktə
                                            </Link>
                                            <button
                                                @click="deleteExam(exam)"
                                                class="text-red-600 hover:text-red-900"
                                            >
                                                Sil
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div v-else class="p-12 text-center">
                        <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                            <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                            </svg>
                        </div>
                        <p class="text-gray-500 mb-4">Hələ imtahan yaratmamısınız</p>
                        <Link
                            :href="route('teacher.exams.create')"
                            class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700"
                        >
                            İlk İmtahanı Yarat
                        </Link>
                    </div>

                    <!-- Pagination -->
                    <div v-if="exams.links && exams.data?.length" class="px-6 py-4 border-t border-gray-200">
                        <div class="flex justify-between items-center">
                            <div class="text-sm text-gray-500">
                                {{ exams.from }} - {{ exams.to }} / {{ exams.total }}
                            </div>
                            <div class="flex gap-2">
                                <Link
                                    v-for="link in exams.links"
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

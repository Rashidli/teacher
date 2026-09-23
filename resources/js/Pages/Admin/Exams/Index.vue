<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    exams: Object,
    subjects: Array,
    groups: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
});

// Səhifə yenilənəndə seçimlər itməsin
const filterSubject = ref(props.filters.subject_id ?? '');
const filterGroup = ref(props.filters.group_id ?? '');
const filterStatus = ref(props.filters.status ?? '');

const applyFilters = () => {
    router.get(
        route('admin.exams.index'),
        // Boş dəyərlər URL-ə düşməsin
        Object.fromEntries(
            Object.entries({
                subject_id: filterSubject.value,
                group_id: filterGroup.value,
                status: filterStatus.value,
            }).filter(([, value]) => value !== '' && value !== null)
        ),
        { preserveState: true, preserveScroll: true, replace: true },
    );
};

const resetFilters = () => {
    filterSubject.value = '';
    filterGroup.value = '';
    filterStatus.value = '';
    applyFilters();
};

const togglePublish = (exam) => {
    useForm({}).post(route('admin.exams.toggle-publish', exam.id));
};

const toggleActive = (exam) => {
    useForm({}).post(route('admin.exams.toggle-active', exam.id));
};
</script>

<template>
    <Head title="İmtahanlar" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                İmtahanlar
            </h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-sm rounded-lg">
                    <!-- Filters -->
                    <div class="p-6 border-b border-gray-200">
                        <div class="flex gap-4">
                            <select
                                v-model="filterSubject"
                                @change="applyFilters"
                                class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            >
                                <option value="">Bütün Fənlər</option>
                                <option v-for="subject in subjects" :key="subject.id" :value="subject.id">
                                    {{ subject.name }}
                                </option>
                            </select>
                            <select
                                v-model="filterGroup"
                                @change="applyFilters"
                                class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            >
                                <option value="">Bütün Qruplar</option>
                                <option v-for="group in groups" :key="group.id" :value="group.id">
                                    {{ group.name }}
                                </option>
                            </select>
                            <select
                                v-model="filterStatus"
                                @change="applyFilters"
                                class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            >
                                <option value="">Bütün Statuslar</option>
                                <option value="published">Dərc Edilib</option>
                                <option value="draft">Dərc Edilməyib</option>
                                <option value="active">Aktiv</option>
                                <option value="inactive">Deaktiv</option>
                            </select>
                            <button
                                v-if="filterSubject || filterGroup || filterStatus"
                                type="button"
                                @click="resetFilters"
                                class="text-sm text-gray-600 hover:text-gray-900"
                            >
                                Filtrləri sıfırla
                            </button>
                        </div>
                        <div class="mt-4 flex gap-2">
                            <Link :href="route('admin.exams.create')"
                                class="px-4 py-2 bg-indigo-600 text-white text-sm rounded-lg hover:bg-indigo-700">
                                + Yeni imtahan
                            </Link>
                            <Link :href="route('admin.exams.generate')"
                                class="px-4 py-2 bg-gray-100 text-gray-800 text-sm rounded-lg hover:bg-gray-200">
                                Bankdan imtahan yarat
                            </Link>
                        </div>
                    </div>

                    <!-- Table -->
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        İmtahan
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Yaradan
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Fənn
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Sual Sayı
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Dərc
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
                                        <div class="flex items-center gap-2">
                                            <span class="text-sm font-medium text-gray-900">{{ exam.title }}</span>
                                            <!-- Nümunə məzmun: yalnız admin tərəfdə görünür (php artisan demo:clear onu silir) -->
                                            <span
                                                v-if="exam.is_demo"
                                                class="rounded bg-amber-100 px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-wide text-amber-800"
                                                title="Nümunə məzmun — php artisan demo:clear ilə silinir"
                                            >demo</span>
                                        </div>
                                        <div class="text-xs text-gray-500">{{ exam.duration_minutes }} dəqiqə</div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        {{ exam.teacher?.full_name ?? exam.creator?.full_name ?? '—' }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-2 py-1 text-xs bg-indigo-100 text-indigo-800 rounded">
                                            {{ exam.subject?.name }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        {{ exam.questions_count || 0 }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <button
                                            @click="togglePublish(exam)"
                                            :class="[
                                                'px-2 py-1 text-xs rounded-full',
                                                exam.is_published
                                                    ? 'bg-green-100 text-green-800'
                                                    : 'bg-yellow-100 text-yellow-800'
                                            ]"
                                        >
                                            {{ exam.is_published ? 'Dərc Edilib' : 'Gözləyir' }}
                                        </button>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <button
                                            @click="toggleActive(exam)"
                                            :class="[
                                                'px-2 py-1 text-xs rounded-full',
                                                exam.is_active
                                                    ? 'bg-green-100 text-green-800'
                                                    : 'bg-gray-100 text-gray-800'
                                            ]"
                                        >
                                            {{ exam.is_active ? 'Aktiv' : 'Deaktiv' }}
                                        </button>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                        <Link
                                            :href="route('admin.exams.show', exam.id)"
                                            class="text-indigo-600 hover:text-indigo-900"
                                        >
                                            Bax
                                        </Link>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <div v-if="exams.links" class="px-6 py-4 border-t border-gray-200">
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

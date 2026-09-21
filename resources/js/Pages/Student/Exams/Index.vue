<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { useFeatures } from '@/Composables/useFeatures';

const props = defineProps({
    exams: Object,
    subjects: Array,
    groups: Array,
    filters: Object,
});

const { teachersEnabled } = useFeatures();

const filterSubject = ref(props.filters?.subject_id || '');
const filterGroup = ref(props.filters?.group_id || '');

const applyFilters = () => {
    router.get(route('student.exams.index'), {
        subject_id: filterSubject.value || undefined,
        group_id: filterGroup.value || undefined,
    }, { preserveState: true, preserveScroll: true });
};
</script>

<template>
    <Head title="İmtahanlar" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                Mövcud İmtahanlar
            </h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <!-- Filters -->
                <div class="bg-white overflow-hidden shadow-sm rounded-lg mb-6">
                    <div class="p-4 sm:p-6">
                        <div class="flex flex-col sm:flex-row gap-3 sm:gap-4">
                            <select
                                v-model="filterSubject"
                                @change="applyFilters"
                                class="w-full sm:w-auto rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            >
                                <option value="">Bütün Fənlər</option>
                                <option v-for="subject in subjects" :key="subject.id" :value="subject.id">
                                    {{ subject.name }}
                                </option>
                            </select>
                            <select
                                v-model="filterGroup"
                                @change="applyFilters"
                                class="w-full sm:w-auto rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            >
                                <option value="">Bütün Qruplar</option>
                                <option v-for="group in groups" :key="group.id" :value="group.id">
                                    {{ group.name }}
                                </option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Exams Grid -->
                <div v-if="exams.data?.length" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <div
                        v-for="exam in exams.data"
                        :key="exam.id"
                        class="bg-white overflow-hidden shadow-sm rounded-lg"
                    >
                        <div class="p-6">
                            <div class="flex items-start justify-between mb-4">
                                <span class="px-2 py-1 text-xs bg-indigo-100 text-indigo-800 rounded">
                                    {{ exam.subject?.name }}
                                </span>
                                <span class="px-2 py-1 text-xs bg-green-100 text-green-800 rounded">
                                    {{ exam.group?.name }}
                                </span>
                            </div>
                            <h3 class="text-lg font-semibold text-gray-900 mb-2">{{ exam.title }}</h3>
                            <p v-if="exam.description" class="text-sm text-gray-500 mb-4 line-clamp-2">
                                {{ exam.description }}
                            </p>
                            <div class="flex items-center justify-between text-sm text-gray-500 mb-4">
                                <span>{{ exam.questions_count }} sual</span>
                                <span>{{ exam.duration_minutes }} dəqiqə</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-sm text-gray-500">
                                    <template v-if="teachersEnabled">{{ exam.teacher?.full_name }}</template>
                                </span>
                                <Link
                                    :href="route('student.exams.show', exam.id)"
                                    class="px-4 py-2 bg-indigo-600 text-white text-sm rounded-lg hover:bg-indigo-700"
                                >
                                    Baxış
                                </Link>
                            </div>
                        </div>
                    </div>
                </div>

                <div v-else class="bg-white overflow-hidden shadow-sm rounded-lg">
                    <div class="p-12 text-center">
                        <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                            <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                            </svg>
                        </div>
                        <p class="text-gray-500">Hazırda mövcud imtahan yoxdur</p>
                    </div>
                </div>

                <!-- Pagination -->
                <div v-if="exams.links && exams.data?.length" class="mt-6 flex justify-center gap-2">
                    <Link
                        v-for="link in exams.links"
                        :key="link.label"
                        :href="link.url"
                        :class="[
                            'px-3 py-1 text-sm rounded',
                            link.active ? 'bg-indigo-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-100',
                            !link.url && 'opacity-50 cursor-not-allowed'
                        ]"
                        v-html="link.label"
                    />
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

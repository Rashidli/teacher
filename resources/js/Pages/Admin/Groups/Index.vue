<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    groups: Array,
    subjects: Array,
    scores: Object,
});

const editingGroup = ref(null);
const form = useForm({
    scores: {},
});

const openEdit = (group) => {
    editingGroup.value = group;
    form.scores = {};
    props.subjects.forEach(subject => {
        const existingScore = props.scores[group.id]?.[subject.id];
        form.scores[subject.id] = existingScore || 0;
    });
};

const closeEdit = () => {
    editingGroup.value = null;
    form.reset();
};

const submit = () => {
    form.put(route('admin.groups.update-scores', editingGroup.value.id), {
        onSuccess: () => closeEdit()
    });
};

const getScore = (groupId, subjectId) => {
    return props.scores[groupId]?.[subjectId] || '-';
};
</script>

<template>
    <Head title="Qruplar və Ballar" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                Qruplar və Bal Matrisi
            </h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <!-- Edit Modal -->
                <div v-if="editingGroup" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
                    <div class="bg-white rounded-lg p-6 w-full max-w-2xl max-h-[90vh] overflow-y-auto">
                        <h3 class="text-lg font-semibold mb-4">
                            {{ editingGroup.name }} Qrupu Balları
                        </h3>
                        <form @submit.prevent="submit">
                            <div class="grid grid-cols-2 gap-4">
                                <div
                                    v-for="subject in subjects"
                                    :key="subject.id"
                                    class="flex items-center justify-between p-3 bg-gray-50 rounded-lg"
                                >
                                    <span class="text-sm font-medium text-gray-700">{{ subject.name }}</span>
                                    <input
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        v-model="form.scores[subject.id]"
                                        class="w-20 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                                    />
                                </div>
                            </div>
                            <div class="flex justify-end gap-3 pt-6">
                                <button
                                    type="button"
                                    @click="closeEdit"
                                    class="px-4 py-2 text-gray-700 hover:text-gray-900"
                                >
                                    Ləğv et
                                </button>
                                <button
                                    type="submit"
                                    :disabled="form.processing"
                                    class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 disabled:opacity-50"
                                >
                                    Yadda Saxla
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Score Matrix -->
                <div class="bg-white overflow-hidden shadow-sm rounded-lg">
                    <div class="p-6 border-b border-gray-200">
                        <p class="text-sm text-gray-600">
                            Hər fənn üçün qrup üzrə düzgün cavaba verilən bal
                        </p>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Fənn
                                    </th>
                                    <th
                                        v-for="group in groups"
                                        :key="group.id"
                                        class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider"
                                    >
                                        {{ group.name }}
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <tr v-for="subject in subjects" :key="subject.id">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium text-gray-900">{{ subject.name }}</div>
                                        <div class="text-xs text-gray-500">
                                            {{ subject.category === 'humanitarian' ? 'Humanitar' : 'Texniki' }}
                                        </div>
                                    </td>
                                    <td
                                        v-for="group in groups"
                                        :key="group.id"
                                        class="px-6 py-4 whitespace-nowrap text-center"
                                    >
                                        <span class="text-sm font-semibold text-gray-900">
                                            {{ getScore(group.id, subject.id) }}
                                        </span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Edit Buttons -->
                    <div class="p-6 border-t border-gray-200">
                        <h4 class="text-sm font-medium text-gray-700 mb-3">Qrup Ballarını Redaktə Et:</h4>
                        <div class="flex gap-3">
                            <button
                                v-for="group in groups"
                                :key="group.id"
                                @click="openEdit(group)"
                                class="px-4 py-2 bg-indigo-100 text-indigo-700 rounded-lg hover:bg-indigo-200"
                            >
                                {{ group.name }} Qrupu
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

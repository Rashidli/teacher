<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';

defineProps({
    subjects: Array,
});

const showForm = ref(false);
const editingSubject = ref(null);

const form = useForm({
    name: '',
    slug: '',
    icon: '',
    category: 'humanitarian',
    order: 0,
});

const openCreate = () => {
    form.reset();
    editingSubject.value = null;
    showForm.value = true;
};

const openEdit = (subject) => {
    editingSubject.value = subject;
    form.name = subject.name;
    form.slug = subject.slug;
    form.icon = subject.icon || '';
    form.category = subject.category;
    form.order = subject.order;
    showForm.value = true;
};

const submit = () => {
    if (editingSubject.value) {
        form.put(route('admin.subjects.update', editingSubject.value.id), {
            onSuccess: () => {
                showForm.value = false;
                form.reset();
            }
        });
    } else {
        form.post(route('admin.subjects.store'), {
            onSuccess: () => {
                showForm.value = false;
                form.reset();
            }
        });
    }
};

const toggleActive = (subject) => {
    useForm({}).post(route('admin.subjects.toggle-active', subject.id));
};

const generateSlug = () => {
    form.slug = form.name
        .toLowerCase()
        .replace(/ə/g, 'e')
        .replace(/ı/g, 'i')
        .replace(/ö/g, 'o')
        .replace(/ü/g, 'u')
        .replace(/ş/g, 's')
        .replace(/ç/g, 'c')
        .replace(/ğ/g, 'g')
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/(^-|-$)/g, '');
};
</script>

<template>
    <Head title="Fənlər" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">
                    Fənlər
                </h2>
                <button
                    @click="openCreate"
                    class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700"
                >
                    Yeni Fənn
                </button>
            </div>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <!-- Form Modal -->
                <div v-if="showForm" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
                    <div class="bg-white rounded-lg p-6 w-full max-w-md">
                        <h3 class="text-lg font-semibold mb-4">
                            {{ editingSubject ? 'Fənni Redaktə Et' : 'Yeni Fənn' }}
                        </h3>
                        <form @submit.prevent="submit" class="space-y-4">
                            <div>
                                <InputLabel for="name" value="Ad" />
                                <TextInput
                                    id="name"
                                    v-model="form.name"
                                    @input="generateSlug"
                                    class="mt-1 block w-full"
                                    required
                                />
                                <InputError :message="form.errors.name" class="mt-2" />
                            </div>

                            <div>
                                <InputLabel for="slug" value="Slug" />
                                <TextInput
                                    id="slug"
                                    v-model="form.slug"
                                    class="mt-1 block w-full"
                                    required
                                />
                                <InputError :message="form.errors.slug" class="mt-2" />
                            </div>

                            <div>
                                <InputLabel for="category" value="Kateqoriya" />
                                <select
                                    id="category"
                                    v-model="form.category"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                >
                                    <option value="humanitarian">Humanitar</option>
                                    <option value="technical">Texniki</option>
                                </select>
                                <InputError :message="form.errors.category" class="mt-2" />
                            </div>

                            <div>
                                <InputLabel for="order" value="Sıra" />
                                <TextInput
                                    id="order"
                                    type="number"
                                    v-model="form.order"
                                    class="mt-1 block w-full"
                                />
                                <InputError :message="form.errors.order" class="mt-2" />
                            </div>

                            <div class="flex justify-end gap-3 pt-4">
                                <button
                                    type="button"
                                    @click="showForm = false"
                                    class="px-4 py-2 text-gray-700 hover:text-gray-900"
                                >
                                    Ləğv et
                                </button>
                                <button
                                    type="submit"
                                    :disabled="form.processing"
                                    class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 disabled:opacity-50"
                                >
                                    {{ editingSubject ? 'Yenilə' : 'Yarat' }}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Subjects Table -->
                <div class="bg-white overflow-hidden shadow-sm rounded-lg">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Sıra
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Ad
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Slug
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Kateqoriya
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
                                <tr v-for="subject in subjects" :key="subject.id">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        {{ subject.order }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium text-gray-900">{{ subject.name }}</div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        {{ subject.slug }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span
                                            :class="[
                                                'px-2 py-1 text-xs rounded-full',
                                                subject.category === 'humanitarian'
                                                    ? 'bg-blue-100 text-blue-800'
                                                    : 'bg-purple-100 text-purple-800'
                                            ]"
                                        >
                                            {{ subject.category === 'humanitarian' ? 'Humanitar' : 'Texniki' }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <button
                                            @click="toggleActive(subject)"
                                            :class="[
                                                'px-2 py-1 text-xs rounded-full',
                                                subject.is_active
                                                    ? 'bg-green-100 text-green-800'
                                                    : 'bg-gray-100 text-gray-800'
                                            ]"
                                        >
                                            {{ subject.is_active ? 'Aktiv' : 'Deaktiv' }}
                                        </button>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                        <button
                                            @click="openEdit(subject)"
                                            class="text-indigo-600 hover:text-indigo-900"
                                        >
                                            Redaktə
                                        </button>
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

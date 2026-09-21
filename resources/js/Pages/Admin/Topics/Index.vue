<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    topics: { type: Array, default: () => [] },
    subjects: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
});

const subjectFilter = ref(props.filters.subject_id ?? '');

const form = useForm({
    subject_id: props.filters.subject_id ?? '',
    name: '',
    quarter: '',
    order: 0,
    is_active: true,
});

const editing = ref(null);
const editForm = useForm({ subject_id: '', name: '', quarter: '', order: 0, is_active: true });

const applyFilter = () => {
    router.get(route('admin.topics.index'),
        subjectFilter.value ? { subject_id: subjectFilter.value } : {},
        { preserveState: true, replace: true });
};

const submit = () => form.post(route('admin.topics.store'), {
    preserveScroll: true,
    onSuccess: () => form.reset('name', 'quarter', 'order'),
});

const startEdit = (topic) => {
    editing.value = topic.id;
    editForm.subject_id = topic.subject_id;
    editForm.name = topic.name;
    editForm.quarter = topic.quarter ?? '';
    editForm.order = topic.order;
    editForm.is_active = topic.is_active;
};

const saveEdit = (topic) => editForm.put(route('admin.topics.update', topic.id), {
    preserveScroll: true,
    onSuccess: () => { editing.value = null; },
});

const remove = (topic) => {
    if (confirm(`"${topic.name}" mövzusu silinsin?`)) {
        router.delete(route('admin.topics.destroy', topic.id), { preserveScroll: true });
    }
};
</script>

<template>
    <Head title="Mövzular" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">Fənn mövzuları</h2>
                <Link :href="route('admin.questions.index')" class="text-sm text-indigo-600 hover:text-indigo-800">
                    Sual bankı
                </Link>
            </div>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-5xl sm:px-6 lg:px-8 space-y-6">
                <!-- Yeni mövzu -->
                <div class="bg-white shadow-sm rounded-lg p-6">
                    <h3 class="text-lg font-semibold text-gray-900">Yeni mövzu</h3>
                    <p class="mt-1 text-sm text-gray-600">
                        Rüb yalnız məktəb fənlərində doldurulur — sürücülük və dövlət qulluğu
                        mövzularında boş qalır.
                    </p>

                    <form @submit.prevent="submit" class="mt-4 grid gap-4 sm:grid-cols-4">
                        <div class="sm:col-span-2">
                            <InputLabel for="subject_id" value="Fənn" />
                            <select id="subject_id" v-model="form.subject_id" required
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm">
                                <option value="">Fənn seçin</option>
                                <option v-for="subject in subjects" :key="subject.id" :value="subject.id">
                                    {{ subject.name }}
                                </option>
                            </select>
                            <InputError :message="form.errors.subject_id" class="mt-2" />
                        </div>

                        <div class="sm:col-span-2">
                            <InputLabel for="name" value="Mövzu adı" />
                            <TextInput id="name" v-model="form.name" type="text" required class="mt-1 block w-full" />
                            <InputError :message="form.errors.name" class="mt-2" />
                        </div>

                        <div>
                            <InputLabel for="quarter" value="Rüb (1–4, istəyə bağlı)" />
                            <TextInput id="quarter" v-model="form.quarter" type="number" min="1" max="4"
                                class="mt-1 block w-full" />
                            <InputError :message="form.errors.quarter" class="mt-2" />
                        </div>

                        <div>
                            <InputLabel for="order" value="Sıra" />
                            <TextInput id="order" v-model="form.order" type="number" min="0" class="mt-1 block w-full" />
                        </div>

                        <div class="sm:col-span-2 flex items-end justify-end">
                            <PrimaryButton :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                                Əlavə et
                            </PrimaryButton>
                        </div>
                    </form>
                </div>

                <!-- Siyahı -->
                <div class="bg-white shadow-sm rounded-lg">
                    <div class="p-4 border-b border-gray-200 flex items-center gap-3">
                        <select v-model="subjectFilter" @change="applyFilter"
                            class="rounded-md border-gray-300 shadow-sm text-sm">
                            <option value="">Bütün fənlər</option>
                            <option v-for="subject in subjects" :key="subject.id" :value="subject.id">
                                {{ subject.name }}
                            </option>
                        </select>
                        <span class="text-sm text-gray-500">{{ topics.length }} mövzu</span>
                    </div>

                    <p v-if="!topics.length" class="p-6 text-sm text-gray-500">Mövzu yoxdur.</p>

                    <table v-else class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-2 text-left font-medium text-gray-500">Fənn</th>
                                <th class="px-4 py-2 text-left font-medium text-gray-500">Mövzu</th>
                                <th class="px-4 py-2 text-left font-medium text-gray-500">Rüb</th>
                                <th class="px-4 py-2 text-left font-medium text-gray-500">Sual</th>
                                <th class="px-4 py-2"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="topic in topics" :key="topic.id" :class="topic.is_active ? '' : 'text-gray-400'">
                                <td class="px-4 py-2">{{ topic.subject }}</td>
                                <td class="px-4 py-2">
                                    <template v-if="editing === topic.id">
                                        <TextInput v-model="editForm.name" type="text" class="w-full text-sm" />
                                        <InputError :message="editForm.errors.name" class="mt-1" />
                                    </template>
                                    <template v-else>{{ topic.name }}</template>
                                </td>
                                <td class="px-4 py-2">
                                    <TextInput v-if="editing === topic.id" v-model="editForm.quarter" type="number"
                                        min="1" max="4" class="w-20 text-sm" />
                                    <template v-else>{{ topic.quarter ?? '—' }}</template>
                                </td>
                                <td class="px-4 py-2">{{ topic.questions_count }}</td>
                                <td class="px-4 py-2 text-right whitespace-nowrap">
                                    <template v-if="editing === topic.id">
                                        <button type="button" @click="saveEdit(topic)"
                                            class="text-green-700 hover:text-green-900">Saxla</button>
                                        <button type="button" @click="editing = null"
                                            class="ml-3 text-gray-500 hover:text-gray-700">Ləğv</button>
                                    </template>
                                    <template v-else>
                                        <button type="button" @click="startEdit(topic)"
                                            class="text-indigo-600 hover:text-indigo-800">Redaktə</button>
                                        <button v-if="!topic.questions_count" type="button" @click="remove(topic)"
                                            class="ml-3 text-red-600 hover:text-red-800">Sil</button>
                                    </template>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

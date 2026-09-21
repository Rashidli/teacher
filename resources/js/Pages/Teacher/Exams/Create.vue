<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

defineProps({
    subjects: Array,
    groups: Array,
});

const form = useForm({
    title: '',
    description: '',
    subject_id: '',
    group_id: '',
    duration_minutes: 60,
    is_active: true,
});

const submit = () => {
    form.post(route('teacher.exams.store'));
};
</script>

<template>
    <Head title="Yeni İmtahan" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">
                    Yeni İmtahan Yarat
                </h2>
                <Link
                    :href="route('teacher.exams.index')"
                    class="text-sm text-indigo-600 hover:text-indigo-800"
                >
                    Geri
                </Link>
            </div>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-2xl sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-sm rounded-lg">
                    <form @submit.prevent="submit" class="p-6 space-y-6">
                        <div>
                            <InputLabel for="title" value="İmtahan Başlığı" />
                            <TextInput
                                id="title"
                                v-model="form.title"
                                type="text"
                                class="mt-1 block w-full"
                                required
                                placeholder="Məs: Tarix I yarımillik imtahanı"
                            />
                            <InputError :message="form.errors.title" class="mt-2" />
                        </div>

                        <div>
                            <InputLabel for="description" value="Təsvir (istəyə bağlı)" />
                            <textarea
                                id="description"
                                v-model="form.description"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                rows="3"
                                placeholder="İmtahan haqqında qısa məlumat..."
                            ></textarea>
                            <InputError :message="form.errors.description" class="mt-2" />
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <InputLabel for="subject_id" value="Fənn" />
                                <select
                                    id="subject_id"
                                    v-model="form.subject_id"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    required
                                >
                                    <option value="">Fənn seçin</option>
                                    <option v-for="subject in subjects" :key="subject.id" :value="subject.id">
                                        {{ subject.name }}
                                    </option>
                                </select>
                                <InputError :message="form.errors.subject_id" class="mt-2" />
                            </div>

                            <div>
                                <InputLabel for="group_id" value="Qrup" />
                                <select
                                    id="group_id"
                                    v-model="form.group_id"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    required
                                >
                                    <option value="">Qrup seçin</option>
                                    <option v-for="group in groups" :key="group.id" :value="group.id">
                                        {{ group.name }} Qrup
                                    </option>
                                </select>
                                <InputError :message="form.errors.group_id" class="mt-2" />
                            </div>
                        </div>

                        <div>
                            <InputLabel for="duration_minutes" value="Müddət (dəqiqə)" />
                            <TextInput
                                id="duration_minutes"
                                v-model="form.duration_minutes"
                                type="number"
                                min="10"
                                max="180"
                                class="mt-1 block w-full"
                                required
                            />
                            <p class="mt-1 text-sm text-gray-500">10-180 dəqiqə arası</p>
                            <InputError :message="form.errors.duration_minutes" class="mt-2" />
                        </div>

                        <div class="flex items-center">
                            <input
                                id="is_active"
                                v-model="form.is_active"
                                type="checkbox"
                                class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500"
                            />
                            <label for="is_active" class="ml-2 text-sm text-gray-600">
                                İmtahan aktiv olsun
                            </label>
                        </div>

                        <div class="flex justify-end gap-4 pt-4 border-t border-gray-200">
                            <Link
                                :href="route('teacher.exams.index')"
                                class="px-4 py-2 text-gray-700 hover:text-gray-900"
                            >
                                Ləğv et
                            </Link>
                            <PrimaryButton
                                :class="{ 'opacity-25': form.processing }"
                                :disabled="form.processing"
                            >
                                İmtahan Yarat
                            </PrimaryButton>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

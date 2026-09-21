<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    exam: Object,
    subjects: Array,
});

const form = useForm({
    title: props.exam.title,
    description: props.exam.description || '',
    subject_id: props.exam.subject_id,
    duration_minutes: props.exam.duration_minutes,
    is_active: props.exam.is_active,
});

const submit = () => {
    form.put(route('teacher.exams.update', props.exam.id));
};
</script>

<template>
    <Head :title="`Redaktə: ${exam.title}`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">
                    İmtahanı Redaktə Et
                </h2>
                <Link
                    :href="route('teacher.exams.show', exam.id)"
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
                            ></textarea>
                            <InputError :message="form.errors.description" class="mt-2" />
                        </div>

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
                            <InputLabel for="duration_minutes" value="Müddət (dəqiqə)" />
                            <TextInput
                                id="duration_minutes"
                                v-model="form.duration_minutes"
                                type="number"
                                min="5"
                                max="300"
                                class="mt-1 block w-full"
                                required
                            />
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
                                :href="route('teacher.exams.show', exam.id)"
                                class="px-4 py-2 text-gray-700 hover:text-gray-900"
                            >
                                Ləğv et
                            </Link>
                            <PrimaryButton
                                :class="{ 'opacity-25': form.processing }"
                                :disabled="form.processing"
                            >
                                Yadda Saxla
                            </PrimaryButton>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

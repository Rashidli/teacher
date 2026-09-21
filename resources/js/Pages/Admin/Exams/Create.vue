<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { watch } from 'vue';

const props = defineProps({
    // Müəllim modulu söndürülübsə boş massiv gəlir və müəllim seçimi göstərilmir
    teachers: { type: Array, default: () => [] },
    subjects: { type: Array, default: () => [] },
    groups: { type: Array, default: () => [] },
    // false: .env-də EXAM_OWNER_ID yoxdur, imtahan yaradıla bilməz
    ownerConfigured: { type: Boolean, default: true },
});

const form = useForm({
    teacher_id: '',
    subject_id: '',
    group_id: '',
    title: '',
    description: '',
    duration_minutes: 60,
    is_free: true,
    price: 0,
});

// Pulsuz imtahanın qiyməti saxlanılmır
watch(() => form.is_free, (isFree) => {
    if (isFree) {
        form.price = 0;
    }
});

const submit = () => {
    form.post(route('admin.exams.store'));
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
                    :href="route('admin.exams.index')"
                    class="text-sm text-indigo-600 hover:text-indigo-800"
                >
                    Geri
                </Link>
            </div>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-2xl sm:px-6 lg:px-8">
                <div
                    v-if="!ownerConfigured && teachers.length === 0"
                    class="mb-6 rounded-lg border border-amber-300 bg-amber-50 p-4 text-sm text-amber-800"
                >
                    <p class="font-semibold">İmtahan sahibi təyin olunmayıb</p>
                    <p class="mt-1">
                        Müəllim modulu söndürülüb, ona görə imtahanlar admin hesabına bağlanır.
                        <code class="rounded bg-amber-100 px-1">.env</code> faylında
                        <code class="rounded bg-amber-100 px-1">EXAM_OWNER_ID</code> mövcud admin hesabının
                        ID-si olmalıdır. Dəyişiklikdən sonra
                        <code class="rounded bg-amber-100 px-1">php artisan config:clear</code>.
                    </p>
                </div>

                <div class="bg-white overflow-hidden shadow-sm rounded-lg">
                    <form @submit.prevent="submit" class="p-6 space-y-6">
                        <InputError :message="form.errors.exam_owner" class="mb-2" />

                        <div v-if="teachers.length">
                            <InputLabel for="teacher_id" value="Müəllim" />
                            <select
                                id="teacher_id"
                                v-model="form.teacher_id"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                required
                            >
                                <option value="">Müəllim seçin</option>
                                <option v-for="teacher in teachers" :key="teacher.id" :value="teacher.id">
                                    {{ teacher.full_name ?? teacher.name }}
                                </option>
                            </select>
                            <InputError :message="form.errors.teacher_id" class="mt-2" />
                        </div>

                        <div>
                            <InputLabel for="title" value="İmtahan Başlığı" />
                            <TextInput
                                id="title"
                                v-model="form.title"
                                type="text"
                                class="mt-1 block w-full"
                                required
                                placeholder="Məs: Riyaziyyat I yarımillik sınaq imtahanı"
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
                                        {{ group.name }}
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

                        <div class="rounded-md border border-gray-200 p-4 space-y-4">
                            <div class="flex items-center">
                                <input
                                    id="is_free"
                                    v-model="form.is_free"
                                    type="checkbox"
                                    class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500"
                                />
                                <label for="is_free" class="ml-2 text-sm text-gray-600">
                                    Pulsuz imtahan
                                </label>
                            </div>

                            <div v-if="!form.is_free">
                                <InputLabel for="price" value="Qiymət (AZN)" />
                                <TextInput
                                    id="price"
                                    v-model="form.price"
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    class="mt-1 block w-full"
                                />
                                <InputError :message="form.errors.price" class="mt-2" />
                            </div>
                        </div>

                        <p class="text-sm text-gray-500">
                            İmtahan qaralama kimi yaradılır. Sual əlavə etdikdən sonra imtahan səhifəsindən
                            aktivləşdirib yayımlaya bilərsiniz.
                        </p>

                        <div class="flex justify-end gap-4 pt-4 border-t border-gray-200">
                            <Link
                                :href="route('admin.exams.index')"
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

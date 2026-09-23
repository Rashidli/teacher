<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import TagPicker from '@/Components/Admin/TagPicker.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { watch } from 'vue';

const props = defineProps({
    tags: { type: Array, default: () => [] },
    exam: { type: Object, required: true },
    categories: { type: Array, default: () => [] },
    // Sual bağlanıbsa sektor dəyişmir: suallar başqa dildə qalardı
    sectorLocked: { type: Boolean, default: false },
});

/*
 * Fənn, qrup və müəllim redaktə edilmir: imtahanda artıq suallar və şagird cəhdləri ola bilər,
 * fənn/qrup dəyişsə bal hesablaması mənasını itirir. Dəyişmək lazımdırsa yeni imtahan yaradılır.
 */
const form = useForm({
    category_id: props.exam.category_id ?? '',
    tags: (props.exam.tags ?? []).map((tag) => tag.id),
    title: props.exam.title,
    description: props.exam.description ?? '',
    duration_minutes: props.exam.duration_minutes,
    sector: props.exam.sector ?? 'az',
    is_free: Boolean(props.exam.is_free),
    price: Number(props.exam.price ?? 0),
    is_active: Boolean(props.exam.is_active),
});

watch(() => form.is_free, (isFree) => {
    if (isFree) {
        form.price = 0;
    }
});

const submit = () => {
    form.put(route('admin.exams.update', props.exam.id));
};
</script>

<template>
    <Head :title="`${exam.title} — redaktə`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">
                    İmtahanı Redaktə Et
                </h2>
                <Link
                    :href="route('admin.exams.show', exam.id)"
                    class="text-sm text-indigo-600 hover:text-indigo-800"
                >
                    Geri
                </Link>
            </div>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-2xl sm:px-6 lg:px-8 space-y-6">
                <div class="bg-white shadow-sm rounded-lg p-6">
                    <h3 class="text-sm font-semibold text-gray-700">Dəyişdirilə bilməyən sahələr</h3>
                    <dl class="mt-3 grid grid-cols-2 gap-4 text-sm">
                        <div>
                            <dt class="text-gray-500">Fənn</dt>
                            <dd class="text-gray-900">{{ exam.subject?.name ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-gray-500">Qrup</dt>
                            <dd class="text-gray-900">{{ exam.group?.name ?? '—' }}</dd>
                        </div>
                    </dl>
                    <p class="mt-3 text-xs text-gray-500">
                        Fənn və qrup dəyişdirilmir: imtahanda artıq suallar və şagird cəhdləri ola bilər.
                        Başqa fənn üçün yeni imtahan yaradın.
                    </p>
                </div>

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
                            <InputLabel for="category_id" value="Kateqoriya" />
                            <select
                                id="category_id"
                                v-model="form.category_id"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            >
                                <option value="">— seçilməyib —</option>
                                <option v-for="category in categories" :key="category.id" :value="category.id">
                                    {{ category.label }}
                                </option>
                            </select>
                            <InputError :message="form.errors.category_id" class="mt-2" />
                        </div>

                        <!-- Etiketlər: sinif səviyyəsi və sərbəst etiketlər -->
                        <TagPicker v-model="form.tags" :tags="tags" :error="form.errors.tags" />


                        <div>
                            <InputLabel for="sector" value="Tədris sektoru" />
                            <select
                                id="sector"
                                v-model="form.sector"
                                :disabled="sectorLocked"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 disabled:bg-gray-100"
                            >
                                <option value="az">Azərbaycan sektoru</option>
                                <option value="ru">Rus sektoru</option>
                            </select>
                            <p class="mt-1 text-sm text-gray-500">{{ sectorLocked ? 'Sual bağlanıb: sektor dəyişdirilmir.' : 'İmtahana yalnız bu dildə suallar bağlana bilər.' }}</p>
                            <InputError :message="form.errors.sector" class="mt-2" />
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
                                    min="0.01"
                                    step="0.01"
                                    required
                                    class="mt-1 block w-full"
                                />
                                <p class="mt-1 text-xs text-gray-500">
                                    Ödənişli imtahanın qiyməti sıfırdan böyük olmalıdır.
                                </p>
                                <InputError :message="form.errors.price" class="mt-2" />
                            </div>
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
                                :href="route('admin.exams.show', exam.id)"
                                class="px-4 py-2 text-gray-700 hover:text-gray-900"
                            >
                                Ləğv et
                            </Link>
                            <PrimaryButton
                                :class="{ 'opacity-25': form.processing }"
                                :disabled="form.processing"
                            >
                                Yadda saxla
                            </PrimaryButton>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

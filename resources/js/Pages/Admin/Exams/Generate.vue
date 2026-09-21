<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';

const props = defineProps({
    categories: { type: Array, default: () => [] },
});

const form = useForm({
    category_id: '',
    // Sektor həm fənn siyahısını, həm də sual hovuzunun dilini təyin edir
    sector: 'az',
    quarter: '',
    is_cumulative: false,
    variants: 1,
    title: '',
    duration_minutes: 180,
    options_per_question: 5,
    counts: {},
    // Xarici dil: bir imtahana yalnız bir dil düşür
    language_subject_id: '',
});

const category = computed(() => props.categories.find((item) => item.id === form.category_id) ?? null);
const subjects = computed(() => category.value?.subjects?.[form.sector] ?? []);
const languageSubjects = computed(() => subjects.value.filter((subject) => subject.is_language));
const plainSubjects = computed(() => subjects.value.filter((subject) => !subject.is_language));

// Kateqoriya və ya sektor dəyişəndə fənn siyahısı dəyişir: saylar sıfırlanır
watch([() => form.category_id, () => form.sector], () => {
    form.counts = {};
    form.language_subject_id = '';
});

// Yalnız seçilmiş dil göndərilir
const submit = () => {
    const counts = { ...form.counts };

    languageSubjects.value.forEach((subject) => {
        if (String(subject.id) !== String(form.language_subject_id)) {
            delete counts[subject.id];
        }
    });

    form.transform((data) => ({ ...data, counts })).post(route('admin.exams.generate.store'));
};

const totalQuestions = computed(() => Object.entries(form.counts)
    .filter(([id]) => !languageSubjects.value.some((subject) => String(subject.id) === String(id))
        || String(id) === String(form.language_subject_id))
    .reduce((sum, [, value]) => sum + (Number(value) || 0), 0));
</script>

<template>
    <Head title="Bankdan imtahan yarat" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">Bankdan imtahan yarat</h2>
                <Link :href="route('admin.exams.index')" class="text-sm text-indigo-600 hover:text-indigo-800">
                    Geri
                </Link>
            </div>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-3xl sm:px-6 lg:px-8">
                <div v-if="form.errors.bank" class="mb-6 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800">
                    <p class="font-semibold">Bankda kifayət qədər sual yoxdur — heç nə yaradılmadı</p>
                    <p class="mt-1">{{ form.errors.bank }}</p>
                </div>

                <form @submit.prevent="submit" class="bg-white shadow-sm rounded-lg p-6 space-y-6">
                    <div>
                        <InputLabel for="category_id" value="Kateqoriya (qrup və ya altqrup)" />
                        <select id="category_id" v-model="form.category_id" required
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm">
                            <option value="">Seçin</option>
                            <option v-for="item in categories" :key="item.id" :value="item.id">{{ item.label }}</option>
                        </select>
                        <InputError :message="form.errors.category_id" class="mt-2" />
                    </div>

                    <div>
                        <InputLabel for="sector" value="Tədris sektoru" />
                        <select id="sector" v-model="form.sector" required
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm">
                            <option value="az">Azərbaycan sektoru</option>
                            <option value="ru">Rus sektoru</option>
                        </select>
                        <p class="mt-1 text-xs text-gray-500">
                            Suallar yalnız bu dildəki bankdan seçilir, yaradılan imtahan da bu sektora aid olur.
                        </p>
                        <InputError :message="form.errors.sector" class="mt-2" />
                    </div>

                    <div v-if="category" class="grid gap-4 sm:grid-cols-3">
                        <div>
                            <InputLabel for="quarter" value="Rüb (boş = bütün mövzular)" />
                            <select id="quarter" v-model="form.quarter"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm">
                                <option value="">Rüb seçilməyib</option>
                                <option v-for="n in 4" :key="n" :value="n">{{ n }}-ci rüb</option>
                            </select>
                        </div>

                        <label class="flex items-end gap-2 pb-2">
                            <input v-model="form.is_cumulative" type="checkbox" :disabled="!form.quarter"
                                class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" />
                            <span class="text-sm text-gray-700">Kumulyativ (1-ci rübdən bura qədər)</span>
                        </label>

                        <div>
                            <InputLabel for="variants" value="Neçə variant" />
                            <TextInput id="variants" v-model="form.variants" type="number" min="1" max="10"
                                class="mt-1 block w-full" />
                            <p class="mt-1 text-xs text-gray-500">Variantlar arasında suallar təkrarlanmır.</p>
                        </div>
                    </div>

                    <!-- Fənlər -->
                    <div v-if="category">
                        <InputLabel value="Hər fənn üçün sual sayı" />

                        <div class="mt-2 space-y-2">
                            <div v-for="subject in plainSubjects" :key="subject.id"
                                class="flex items-center justify-between gap-4 rounded-md border border-gray-200 px-3 py-2">
                                <span class="text-sm text-gray-800">{{ subject.name }}</span>
                                <TextInput v-model="form.counts[subject.id]" type="number" min="0" max="200"
                                    class="w-24 text-sm" placeholder="0" />
                            </div>
                        </div>

                        <!-- Xarici dil: tək seçim -->
                        <div v-if="languageSubjects.length" class="mt-4 rounded-md border border-gray-200 p-3">
                            <p class="text-sm font-medium text-gray-700">Xarici dil (yalnız biri)</p>
                            <p class="mt-1 text-xs text-gray-500">
                                Hər dil üçün ayrıca imtahan yaradılır.
                            </p>

                            <div class="mt-3 space-y-2">
                                <label v-for="subject in languageSubjects" :key="subject.id"
                                    class="flex items-center justify-between gap-4">
                                    <span class="flex items-center gap-2 text-sm text-gray-800">
                                        <input type="radio" v-model="form.language_subject_id" :value="subject.id"
                                            class="text-indigo-600 border-gray-300 focus:ring-indigo-500" />
                                        {{ subject.name }}
                                    </span>
                                    <TextInput v-model="form.counts[subject.id]" type="number" min="0" max="200"
                                        :disabled="String(form.language_subject_id) !== String(subject.id)"
                                        class="w-24 text-sm disabled:bg-gray-100" placeholder="0" />
                                </label>
                            </div>
                        </div>

                        <p class="mt-3 text-sm text-gray-600">
                            Cəmi: <strong>{{ totalQuestions }}</strong> sual
                            <template v-if="form.variants > 1">
                                × {{ form.variants }} variant = {{ totalQuestions * form.variants }} unikal sual lazımdır
                            </template>
                        </p>
                        <InputError :message="form.errors.counts" class="mt-2" />
                    </div>

                    <div v-if="category" class="grid gap-4 sm:grid-cols-3">
                        <div class="sm:col-span-3">
                            <InputLabel for="title" value="Başlıq" />
                            <TextInput id="title" v-model="form.title" type="text" required class="mt-1 block w-full"
                                placeholder="Məs: I qrup RK — 2-ci rüb mövzu sınağı" />
                            <InputError :message="form.errors.title" class="mt-2" />
                        </div>

                        <div>
                            <InputLabel for="duration_minutes" value="Müddət (dəqiqə)" />
                            <TextInput id="duration_minutes" v-model="form.duration_minutes" type="number" min="10"
                                max="300" class="mt-1 block w-full" />
                            <InputError :message="form.errors.duration_minutes" class="mt-2" />
                        </div>

                        <div>
                            <InputLabel for="options_per_question" value="Variant sayı" />
                            <select id="options_per_question" v-model="form.options_per_question"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm">
                                <option :value="4">4</option>
                                <option :value="5">5</option>
                            </select>
                        </div>
                    </div>

                    <div class="flex justify-end gap-4 border-t border-gray-200 pt-4">
                        <Link :href="route('admin.exams.index')" class="px-4 py-2 text-gray-700 hover:text-gray-900">
                            Ləğv et
                        </Link>
                        <PrimaryButton :class="{ 'opacity-25': form.processing }"
                            :disabled="form.processing || !category">
                            Qaralama yarat
                        </PrimaryButton>
                    </div>

                    <p class="text-xs text-gray-500">
                        Yaradılan imtahan qaralamadır: sualları yoxlayıb istədiyinizi əvəz edə,
                        sonra dərc edə bilərsiniz. Dərcdən sonra suallar sabit qalır.
                    </p>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

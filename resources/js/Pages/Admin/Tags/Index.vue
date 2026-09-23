<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, useForm, router } from '@inertiajs/vue3';

/**
 * Etiketlərin idarəsi.
 *
 * Sinif etiketləri (`grade`) seeder ilə gəlir, sərbəst etiketləri admin özü yaradır.
 * İmtahana bağlı etiket SİLİNMİR — deaktiv edilir, əks halda kataloq filtri ilə mövcud
 * imtahanlar arasındakı əlaqə səssizcə itərdi.
 */
const props = defineProps({
    tags: { type: Array, default: () => [] },
    kinds: { type: Array, default: () => [] },
});

const KIND_LABELS = { grade: 'Sinif səviyyəsi', other: 'Sərbəst etiket' };

const form = useForm({ name: '', kind: 'other', order: 0, is_active: true });

const submit = () => form.post(route('admin.tags.store'), {
    preserveScroll: true,
    onSuccess: () => form.reset(),
});

const toggle = (tag) => router.put(
    route('admin.tags.update', tag.id),
    { name: tag.name, kind: tag.kind, order: tag.order, is_active: !tag.is_active },
    { preserveScroll: true },
);

const remove = (tag) => {
    if (confirm(`«${tag.name}» etiketi silinsin?`)) {
        router.delete(route('admin.tags.destroy', tag.id), { preserveScroll: true });
    }
};
</script>

<template>
    <Head title="Etiketlər" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">Etiketlər</h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-4xl space-y-6 sm:px-6 lg:px-8">
                <div class="rounded-lg bg-white p-6 shadow-sm">
                    <p class="text-sm text-gray-600">
                        Etiket kateqoriya ağacına <strong>ortoqonaldır</strong>: kateqoriya imtahanın
                        növünü bildirir (abituriyent II qrup, sürücülük), etiket isə əlavə əlaməti —
                        ən əsası sinif səviyyəsini. Kataloqda ayrıca filtr bölməsi kimi görünür.
                    </p>
                </div>

                <!-- Yeni etiket -->
                <form class="rounded-lg bg-white p-6 shadow-sm" @submit.prevent="submit">
                    <h3 class="mb-4 text-lg font-semibold text-gray-900">Yeni etiket</h3>

                    <div class="grid gap-4 sm:grid-cols-4">
                        <div class="sm:col-span-2">
                            <InputLabel for="name" value="Ad" />
                            <TextInput id="name" v-model="form.name" type="text" class="mt-1 block w-full" required />
                            <InputError :message="form.errors.name" class="mt-2" />
                        </div>
                        <div>
                            <InputLabel for="kind" value="Qrup" />
                            <select
                                id="kind"
                                v-model="form.kind"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            >
                                <option v-for="kind in kinds" :key="kind" :value="kind">
                                    {{ KIND_LABELS[kind] ?? kind }}
                                </option>
                            </select>
                            <InputError :message="form.errors.kind" class="mt-2" />
                        </div>
                        <div>
                            <InputLabel for="order" value="Sıra" />
                            <TextInput id="order" v-model="form.order" type="number" min="0" class="mt-1 block w-full" />
                            <InputError :message="form.errors.order" class="mt-2" />
                        </div>
                    </div>

                    <div class="mt-4 flex justify-end">
                        <button
                            type="submit"
                            class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700 disabled:opacity-50"
                            :disabled="form.processing"
                        >Əlavə et</button>
                    </div>
                </form>

                <!-- Siyahı -->
                <div class="overflow-hidden rounded-lg bg-white shadow-sm">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr class="text-left text-xs uppercase tracking-wider text-gray-500">
                                <th class="px-4 py-3">Ad</th>
                                <th class="px-4 py-3">Qrup</th>
                                <th class="px-4 py-3">Sıra</th>
                                <th class="px-4 py-3">İmtahan</th>
                                <th class="px-4 py-3">Vəziyyət</th>
                                <th class="px-4 py-3 text-right">Əməliyyat</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <tr v-for="tag in tags" :key="tag.id">
                                <td class="px-4 py-3">
                                    <span class="font-medium text-gray-900">{{ tag.name }}</span>
                                    <span class="ml-2 font-mono text-xs text-gray-400">{{ tag.slug }}</span>
                                </td>
                                <td class="px-4 py-3">{{ KIND_LABELS[tag.kind] ?? tag.kind }}</td>
                                <td class="px-4 py-3">{{ tag.order }}</td>
                                <td class="px-4 py-3">{{ tag.exams_count }}</td>
                                <td class="px-4 py-3">
                                    <span
                                        class="rounded px-2 py-0.5 text-xs font-medium"
                                        :class="tag.is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-500'"
                                    >{{ tag.is_active ? 'Aktiv' : 'Deaktiv' }}</span>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <button type="button" class="text-indigo-600 hover:text-indigo-800" @click="toggle(tag)">
                                        {{ tag.is_active ? 'Deaktiv et' : 'Aktiv et' }}
                                    </button>
                                    <button
                                        v-if="!tag.exams_count"
                                        type="button"
                                        class="ml-3 text-red-600 hover:text-red-800"
                                        @click="remove(tag)"
                                    >Sil</button>
                                </td>
                            </tr>
                            <tr v-if="!tags.length">
                                <td colspan="6" class="px-4 py-6 text-center text-gray-500">Etiket yoxdur.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';

const props = defineProps({
    exam: { type: Object, required: true },
    accesses: { type: Array, default: () => [] },
});

const SOURCE_LABELS = {
    payment: 'Ödəniş',
    manual: 'Əl ilə',
    free: 'Pulsuz',
};

const form = useForm({
    student: '',
    note: '',
    expires_at: '',
    attempts_allowed: '',
});

const grant = () => {
    form.post(route('admin.exams.access.store', props.exam.id), {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
};

const revoke = (access) => {
    if (confirm(`${access.student.name} üçün giriş ləğv edilsin?`)) {
        router.delete(route('admin.exams.access.destroy', [props.exam.id, access.id]), {
            preserveScroll: true,
        });
    }
};
</script>

<template>
    <Head :title="`Giriş hüquqları: ${exam.title}`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">
                    Giriş hüquqları: {{ exam.title }}
                </h2>
                <Link :href="route('admin.exams.show', exam.id)" class="text-sm text-indigo-600 hover:text-indigo-800">
                    Geri
                </Link>
            </div>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-5xl sm:px-6 lg:px-8 space-y-6">
                <div v-if="exam.is_free" class="rounded-md border border-blue-200 bg-blue-50 p-4 text-sm text-blue-800">
                    Bu imtahan pulsuzdur — bütün şagirdlər onsuz da aça bilir. Aşağıdakı girişlər
                    yalnız imtahan sonradan ödənişli edilsə işə düşəcək.
                </div>

                <!-- Əl ilə giriş vermə -->
                <div class="bg-white shadow-sm rounded-lg p-6">
                    <h3 class="text-lg font-semibold text-gray-900">Əl ilə giriş ver</h3>
                    <p class="mt-1 text-sm text-gray-600">
                        Köçürmə ilə ödəyən şagird üçün. Qeyd sahəsinə köçürmənin qəbz nömrəsini
                        və ya tarixini yazın — sonra hesabat və mübahisəli hallar üçün lazım olacaq.
                    </p>

                    <form @submit.prevent="grant" class="mt-4 grid gap-4 sm:grid-cols-2">
                        <div class="sm:col-span-2">
                            <InputLabel for="student" value="Şagird (email və ya telefon)" />
                            <TextInput id="student" v-model="form.student" type="text" class="mt-1 block w-full" required
                                placeholder="nümunə@mail.com və ya 055 123 45 67" />
                            <InputError :message="form.errors.student" class="mt-2" />
                        </div>

                        <div class="sm:col-span-2">
                            <InputLabel for="note" value="Qeyd (qəbz nömrəsi, tarix)" />
                            <textarea id="note" v-model="form.note" rows="2"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                                placeholder="Məs: Kapital Bank köçürməsi, qəbz 123456, 21.09.2026"></textarea>
                            <InputError :message="form.errors.note" class="mt-2" />
                        </div>

                        <div>
                            <InputLabel for="expires_at" value="Bitmə tarixi (boş = müddətsiz)" />
                            <TextInput id="expires_at" v-model="form.expires_at" type="datetime-local" class="mt-1 block w-full" />
                            <InputError :message="form.errors.expires_at" class="mt-2" />
                        </div>

                        <div>
                            <InputLabel for="attempts_allowed" value="Cəhd limiti (boş = limitsiz)" />
                            <TextInput id="attempts_allowed" v-model="form.attempts_allowed" type="number" min="1" max="100" class="mt-1 block w-full" />
                            <InputError :message="form.errors.attempts_allowed" class="mt-2" />
                        </div>

                        <div class="sm:col-span-2 flex justify-end">
                            <PrimaryButton :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                                Giriş ver
                            </PrimaryButton>
                        </div>
                    </form>
                </div>

                <!-- Mövcud girişlər -->
                <div class="bg-white shadow-sm rounded-lg p-6">
                    <h3 class="text-lg font-semibold text-gray-900">
                        Girişlər
                        <span class="ml-2 text-sm font-normal text-gray-500">({{ accesses.length }})</span>
                    </h3>

                    <p v-if="!accesses.length" class="mt-3 text-sm text-gray-500">
                        Hələ heç kimə giriş verilməyib.
                    </p>

                    <div v-else class="mt-4 overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-3 py-2 text-left font-medium text-gray-500">Şagird</th>
                                    <th class="px-3 py-2 text-left font-medium text-gray-500">Mənbə</th>
                                    <th class="px-3 py-2 text-left font-medium text-gray-500">Qeyd</th>
                                    <th class="px-3 py-2 text-left font-medium text-gray-500">Müddət</th>
                                    <th class="px-3 py-2 text-left font-medium text-gray-500">Vəziyyət</th>
                                    <th class="px-3 py-2"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <tr v-for="access in accesses" :key="access.id" :class="access.is_active ? '' : 'bg-gray-50 text-gray-500'">
                                    <td class="px-3 py-2 align-top">
                                        <div class="font-medium text-gray-900">{{ access.student.name }}</div>
                                        <div class="text-xs text-gray-500">{{ access.student.email }}</div>
                                        <div v-if="access.student.phone" class="text-xs text-gray-500">{{ access.student.phone }}</div>
                                    </td>
                                    <td class="px-3 py-2 align-top">
                                        {{ SOURCE_LABELS[access.source] ?? access.source }}
                                        <div v-if="access.payment" class="text-xs text-gray-500">
                                            {{ access.payment.amount }} {{ access.payment.currency }} — {{ access.payment.status }}
                                        </div>
                                        <div v-if="access.granted_by" class="text-xs text-gray-500">
                                            {{ access.granted_by }}
                                        </div>
                                    </td>
                                    <td class="px-3 py-2 align-top max-w-xs">{{ access.note || '—' }}</td>
                                    <td class="px-3 py-2 align-top whitespace-nowrap">
                                        {{ access.expires_at || 'müddətsiz' }}
                                        <div v-if="access.attempts_allowed" class="text-xs text-gray-500">
                                            {{ access.attempts_allowed }} cəhd
                                        </div>
                                    </td>
                                    <td class="px-3 py-2 align-top">
                                        <span v-if="access.is_active" class="text-green-700">Aktiv</span>
                                        <span v-else-if="access.revoked_at" class="text-red-700">Ləğv edilib</span>
                                        <span v-else class="text-amber-700">Müddəti bitib</span>
                                    </td>
                                    <td class="px-3 py-2 align-top text-right">
                                        <button v-if="access.is_active" type="button" @click="revoke(access)"
                                            class="text-sm text-red-600 hover:text-red-800">
                                            Ləğv et
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

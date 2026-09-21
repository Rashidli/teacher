<script setup>
/*
 * Sınaq "bank səhifəsi". Real bank əvəzinə burada nəticə seçilir və REAL callback
 * route-u çağırılır — beləliklə bütün axın (pending → callback → paid → giriş) sınanır.
 * Bu səhifə produksiyada mövcud deyil (route qeydiyyatdan keçmir).
 */
import { Head, useForm } from '@inertiajs/vue3';

const props = defineProps({
    payment: { type: Object, required: true },
    callbackUrl: { type: String, required: true },
    reference: { type: String, required: true },
    signatures: { type: Object, required: true },
});

const form = useForm({
    reference: props.reference,
    status: 'success',
    signature: props.signatures.success,
});

const pay = (status) => {
    form.status = status;
    form.signature = props.signatures[status];
    form.post(props.callbackUrl);
};
</script>

<template>
    <Head title="Sınaq ödənişi" />

    <div class="min-h-screen bg-gray-100 flex items-center justify-center p-4">
        <div class="w-full max-w-md bg-white rounded-lg shadow-sm p-6">
            <p class="text-xs font-semibold uppercase tracking-wide text-amber-700 bg-amber-50 border border-amber-200 rounded px-2 py-1 inline-block">
                Sınaq rejimi — real bank deyil
            </p>

            <h1 class="mt-4 text-lg font-semibold text-gray-900">Ödəniş</h1>
            <p class="mt-1 text-sm text-gray-600">{{ payment.title }}</p>

            <div class="mt-4 rounded-md bg-gray-50 border border-gray-200 p-4">
                <div class="flex items-baseline justify-between">
                    <span class="text-sm text-gray-500">Məbləğ</span>
                    <span class="text-2xl font-semibold text-gray-900">
                        {{ payment.amount }} {{ payment.currency }}
                    </span>
                </div>
                <p class="mt-2 text-xs text-gray-400">Ödəniş nömrəsi: {{ payment.id }}</p>
            </div>

            <div class="mt-6 grid grid-cols-2 gap-3">
                <button
                    type="button"
                    @click="pay('success')"
                    :disabled="form.processing"
                    class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 disabled:opacity-40"
                >
                    Uğurlu
                </button>
                <button
                    type="button"
                    @click="pay('failed')"
                    :disabled="form.processing"
                    class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 disabled:opacity-40"
                >
                    Uğursuz
                </button>
            </div>
        </div>
    </div>
</template>

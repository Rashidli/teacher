<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { useLocale } from '@/Composables/useLocale';

// Kabinet siyahısı: yeni imtahan axtarışı kataloqdadır (kateqoriya ağacı), burada filtr yoxdur
defineProps({
    inProgress: { type: Array, default: () => [] },
    available: { type: Array, default: () => [] },
    completed: { type: Array, default: () => [] },
});

const { lroute } = useLocale();

const sourceLabels = {
    payment: 'Ödəniş',
    manual: 'Admin icazəsi',
    free: 'Pulsuz',
};
</script>

<template>
    <Head title="Mənim imtahanlarım" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">Mənim imtahanlarım</h2>
                <Link
                    :href="lroute('exams.catalog')"
                    class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700"
                >
                    Kataloqa keç
                </Link>
            </div>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-5xl space-y-8 sm:px-6 lg:px-8">
                <!-- Davam edən cəhdlər -->
                <section v-if="inProgress.length" class="overflow-hidden rounded-lg bg-white shadow-sm">
                    <h3 class="border-b border-gray-200 p-6 text-lg font-semibold text-gray-900">
                        Davam edən imtahanlar
                    </h3>
                    <ul class="divide-y divide-gray-200">
                        <li v-for="item in inProgress" :key="item.attempt_id" class="flex flex-wrap items-center justify-between gap-3 p-6">
                            <div>
                                <Link :href="item.exam_url" class="font-medium text-gray-900 hover:text-indigo-700">
                                    {{ item.title }}
                                </Link>
                                <p class="text-sm text-amber-700">{{ item.remaining_minutes }} dəqiqə qalıb</p>
                            </div>
                            <Link
                                :href="item.url"
                                class="rounded-md bg-amber-500 px-4 py-2 text-sm font-semibold text-white hover:bg-amber-600"
                            >
                                Davam et
                            </Link>
                        </li>
                    </ul>
                </section>

                <!-- Girişi olan imtahanlar -->
                <section v-if="available.length" class="overflow-hidden rounded-lg bg-white shadow-sm">
                    <h3 class="border-b border-gray-200 p-6 text-lg font-semibold text-gray-900">
                        Girişi olan imtahanlar
                    </h3>
                    <ul class="divide-y divide-gray-200">
                        <li v-for="item in available" :key="item.url" class="flex flex-wrap items-center justify-between gap-3 p-6">
                            <div>
                                <Link :href="item.url" class="font-medium text-gray-900 hover:text-indigo-700">
                                    {{ item.title }}
                                </Link>
                                <p class="text-sm text-gray-500">
                                    {{ sourceLabels[item.source] ?? item.source }}
                                    <template v-if="item.expires_at"> · {{ item.expires_at }} tarixinədək</template>
                                </p>
                            </div>
                            <Link
                                :href="item.url"
                                class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700"
                            >
                                Aç
                            </Link>
                        </li>
                    </ul>
                </section>

                <!-- Tamamlanmış cəhdlər -->
                <section v-if="completed.length" class="overflow-hidden rounded-lg bg-white shadow-sm">
                    <h3 class="border-b border-gray-200 p-6 text-lg font-semibold text-gray-900">
                        Tamamlanmış imtahanlar
                    </h3>
                    <ul class="divide-y divide-gray-200">
                        <li v-for="item in completed" :key="item.attempt_id" class="flex flex-wrap items-center justify-between gap-3 p-6">
                            <div>
                                <Link :href="item.exam_url" class="font-medium text-gray-900 hover:text-indigo-700">
                                    {{ item.title }}
                                </Link>
                                <p class="text-sm text-gray-500">
                                    <template v-if="item.finished_at">{{ item.finished_at }} · </template>
                                    {{ item.relative_score ?? 0 }} / 100
                                </p>
                            </div>
                            <Link :href="item.url" class="text-sm font-medium text-indigo-600 hover:text-indigo-800">
                                Nəticəyə bax
                            </Link>
                        </li>
                    </ul>
                </section>

                <!-- Boş vəziyyət -->
                <section v-if="!inProgress.length && !available.length && !completed.length" class="rounded-lg bg-white p-10 text-center shadow-sm">
                    <p class="text-gray-600">Hələ imtahanın yoxdur. Kataloqdan yeni imtahan seç.</p>
                    <p class="mt-1 text-sm text-gray-500">
                        Kataloqdan hazırlaşdığın bölməni seç və imtahanı aç.
                    </p>
                    <Link
                        :href="lroute('exams.catalog')"
                        class="mt-6 inline-block rounded-md bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700"
                    >
                        Kataloqa keç
                    </Link>
                </section>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

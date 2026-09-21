<script setup>
import { Head, Link } from '@inertiajs/vue3';
</script>

<template>
    <Head title="Təsdiq Gözlənilir" />

    <div class="min-h-screen flex items-center justify-center bg-gray-100">
        <div class="max-w-md w-full bg-white rounded-lg shadow-lg p-8 text-center">
            <div class="w-16 h-16 bg-yellow-100 rounded-full flex items-center justify-center mx-auto mb-6">
                <svg class="w-8 h-8 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </div>

            <h1 class="text-2xl font-bold text-gray-900 mb-4">
                Hesabınız Təsdiqlənir
            </h1>

            <p class="text-gray-600 mb-6">
                Müəllim hesabınız admin tərəfindən təsdiqlənmək üçün gözləyir.
                Təsdiqləndikdən sonra platformanın bütün xüsusiyyətlərindən istifadə edə biləcəksiniz.
            </p>

            <div class="bg-gray-50 rounded-lg p-4 mb-6">
                <p class="text-sm text-gray-500">
                    Təsdiq prosesi adətən 24-48 saat çəkir.
                    Suallarınız varsa, bizimlə əlaqə saxlayın.
                </p>
            </div>

            <div class="flex justify-center gap-4">
                <Link
                    :href="route('profile.edit')"
                    class="px-4 py-2 text-indigo-600 hover:text-indigo-800"
                >
                    Profilə Get
                </Link>
                <Link
                    :href="route('logout')"
                    method="post"
                    as="button"
                    class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200"
                >
                    Çıxış
                </Link>
            </div>
        </div>
    </div>
</template>

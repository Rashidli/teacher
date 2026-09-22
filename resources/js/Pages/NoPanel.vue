<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';

// Hesabın açıq paneli yoxdur — səbəbini izah edirik, ilişib qalmasın
defineProps({
    isTeacher: { type: Boolean, default: false },
    roles: { type: Array, default: () => [] },
});

const logout = useForm({});
</script>

<template>
    <Head title="Panel yoxdur" />

    <div class="min-h-screen flex items-center justify-center bg-gray-100 px-4">
        <div class="max-w-md w-full bg-white rounded-lg shadow-lg p-8 text-center">
            <div class="w-16 h-16 bg-amber-100 rounded-full flex items-center justify-center mx-auto mb-6">
                <svg class="w-8 h-8 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z" />
                </svg>
            </div>

            <h1 class="text-2xl font-bold text-gray-900 mb-4">Hesabınız üçün açıq panel yoxdur</h1>

            <p v-if="isTeacher" class="text-gray-600 mb-6">
                Hesabınız müəllim hesabıdır, müəllim modulu isə hazırda söndürülüb.
                Modul açılana qədər bu hesabla panelə giriş yoxdur.
                Şagird kabinetindən istifadə etmək istəyirsinizsə, admin hesabınıza
                <strong>şagird</strong> rolu əlavə edə bilər.
            </p>
            <p v-else class="text-gray-600 mb-6">
                Hesabınıza hələ rol verilməyib. Admin rol təyin edəndən sonra panel açılacaq.
            </p>

            <div class="bg-gray-50 rounded-lg p-4 mb-6 text-sm text-gray-500">
                <span v-if="roles.length">Hesabın rolu: <strong>{{ roles.join(', ') }}</strong></span>
                <span v-else>Hesabın heç bir rolu yoxdur.</span>
            </div>

            <div class="flex justify-center gap-4">
                <Link :href="route('profile.edit')" class="px-4 py-2 text-indigo-600 hover:text-indigo-800">
                    Profil
                </Link>
                <button
                    type="button"
                    class="px-4 py-2 rounded-md bg-gray-800 text-white hover:bg-gray-700"
                    :disabled="logout.processing"
                    @click="logout.post(route('logout'))"
                >
                    Çıxış
                </button>
            </div>
        </div>
    </div>
</template>

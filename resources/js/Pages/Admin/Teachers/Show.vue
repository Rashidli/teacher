<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    teacher: Object,
});

const verifyForm = useForm({});
const unverifyForm = useForm({});

const verify = () => {
    verifyForm.post(route('admin.teachers.verify', props.teacher.id));
};

const unverify = () => {
    unverifyForm.post(route('admin.teachers.unverify', props.teacher.id));
};
</script>

<template>
    <Head :title="`Müəllim: ${teacher.full_name}`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">
                    Müəllim Profili
                </h2>
                <Link
                    :href="route('admin.teachers.index')"
                    class="text-sm text-indigo-600 hover:text-indigo-800"
                >
                    Geri
                </Link>
            </div>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-sm rounded-lg">
                    <div class="p-6">
                        <!-- Profile Info -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                            <div>
                                <h3 class="text-lg font-semibold text-gray-900 mb-4">Şəxsi Məlumatlar</h3>
                                <dl class="space-y-3">
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500">Ad Soyad</dt>
                                        <dd class="text-sm text-gray-900">{{ teacher.full_name }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500">E-poçt</dt>
                                        <dd class="text-sm text-gray-900">{{ teacher.email }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500">Telefon</dt>
                                        <dd class="text-sm text-gray-900">{{ teacher.phone || 'Qeyd edilməyib' }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500">Qeydiyyat Tarixi</dt>
                                        <dd class="text-sm text-gray-900">
                                            {{ new Date(teacher.created_at).toLocaleDateString('az-AZ') }}
                                        </dd>
                                    </div>
                                </dl>
                            </div>

                            <div>
                                <h3 class="text-lg font-semibold text-gray-900 mb-4">Status & Fənlər</h3>
                                <dl class="space-y-3">
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500">Status</dt>
                                        <dd>
                                            <span
                                                :class="[
                                                    'px-2 py-1 text-xs rounded-full',
                                                    teacher.teacher_profile?.is_verified
                                                        ? 'bg-green-100 text-green-800'
                                                        : 'bg-yellow-100 text-yellow-800'
                                                ]"
                                            >
                                                {{ teacher.teacher_profile?.is_verified ? 'Təsdiqlənib' : 'Gözləyir' }}
                                            </span>
                                        </dd>
                                    </div>
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500 mb-2">Fənlər</dt>
                                        <dd class="flex flex-wrap gap-2">
                                            <span
                                                v-for="subject in teacher.subjects"
                                                :key="subject.id"
                                                class="px-3 py-1 text-sm bg-indigo-100 text-indigo-800 rounded-full"
                                            >
                                                {{ subject.name }}
                                            </span>
                                        </dd>
                                    </div>
                                </dl>
                            </div>
                        </div>

                        <!-- Actions -->
                        <div class="border-t border-gray-200 pt-6">
                            <h3 class="text-lg font-semibold text-gray-900 mb-4">Əməliyyatlar</h3>
                            <div class="flex gap-4">
                                <button
                                    v-if="!teacher.teacher_profile?.is_verified"
                                    @click="verify"
                                    :disabled="verifyForm.processing"
                                    class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 disabled:opacity-50"
                                >
                                    Təsdiqlə
                                </button>
                                <button
                                    v-else
                                    @click="unverify"
                                    :disabled="unverifyForm.processing"
                                    class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 disabled:opacity-50"
                                >
                                    Təsdiqi Ləğv Et
                                </button>
                            </div>
                        </div>

                        <!-- Exams -->
                        <div class="border-t border-gray-200 pt-6 mt-6">
                            <h3 class="text-lg font-semibold text-gray-900 mb-4">İmtahanlar ({{ teacher.exams?.length || 0 }})</h3>
                            <div v-if="teacher.exams?.length" class="space-y-3">
                                <div
                                    v-for="exam in teacher.exams"
                                    :key="exam.id"
                                    class="flex items-center justify-between p-4 bg-gray-50 rounded-lg"
                                >
                                    <div>
                                        <p class="font-medium text-gray-900">{{ exam.title }}</p>
                                        <p class="text-sm text-gray-500">
                                            {{ exam.subject?.name }} - {{ exam.questions_count }} sual
                                        </p>
                                    </div>
                                    <Link
                                        :href="route('admin.exams.show', exam.id)"
                                        class="text-indigo-600 hover:text-indigo-800 text-sm"
                                    >
                                        Bax
                                    </Link>
                                </div>
                            </div>
                            <p v-else class="text-gray-500">Hələ imtahan yaradılmayıb</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

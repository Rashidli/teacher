<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import CategoryForm from '@/Components/Admin/CategoryForm.vue';
import { Head, useForm } from '@inertiajs/vue3';

defineProps({
    parents: { type: Array, default: () => [] },
    groups: { type: Array, default: () => [] },
});

const form = useForm({
    parent_id: null,
    group_id: null,
    name: '',
    slug: '',
    short: '',
    description: '',
    is_active: true,
    has_exams: true,
    order: 0,
    color: '',
    seo_title: '',
    seo_description: '',
    h1: '',
    intro: '',
    ru_enabled: false,
    ru_path: '',
    translations: { name: '', short: '', seo_title: '', seo_description: '', h1: '', intro: '' },
});

const submit = () => form.post(route('admin.categories.store'));
</script>

<template>
    <Head title="Yeni kateqoriya" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">Yeni kateqoriya</h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-3xl sm:px-6 lg:px-8">
                <CategoryForm :form="form" :parents="parents" :groups="groups" submit-label="Yarat" @submit="submit" />
            </div>
        </div>
    </AuthenticatedLayout>
</template>

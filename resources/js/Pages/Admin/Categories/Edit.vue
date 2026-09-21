<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import CategoryForm from '@/Components/Admin/CategoryForm.vue';
import { Head, useForm } from '@inertiajs/vue3';

const props = defineProps({
    category: { type: Object, required: true },
    parents: { type: Array, default: () => [] },
    groups: { type: Array, default: () => [] },
});

const form = useForm({
    parent_id: props.category.parent_id,
    group_id: props.category.group_id,
    name: props.category.name,
    slug: props.category.slug,
    short: props.category.short ?? '',
    description: props.category.description ?? '',
    is_active: Boolean(props.category.is_active),
    has_exams: Boolean(props.category.has_exams),
    order: props.category.order ?? 0,
    seo_title: props.category.seo_title ?? '',
    seo_description: props.category.seo_description ?? '',
    h1: props.category.h1 ?? '',
    intro: props.category.intro ?? '',
    ru_enabled: Boolean(props.category.ru_enabled),
    ru_path: props.category.ru_path ?? '',
    // Rus mətnləri `translations->ru` altında saxlanılır
    translations: {
        name: props.category.translations?.name ?? '',
        short: props.category.translations?.short ?? '',
        seo_title: props.category.translations?.seo_title ?? '',
        seo_description: props.category.translations?.seo_description ?? '',
        h1: props.category.translations?.h1 ?? '',
        intro: props.category.translations?.intro ?? '',
    },
});

const submit = () => form.put(route('admin.categories.update', props.category.id));
</script>

<template>
    <Head :title="`${category.name} — redaktə`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">{{ category.name }}</h2>
                <span class="font-mono text-xs text-gray-500">/{{ category.path }}</span>
            </div>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-3xl sm:px-6 lg:px-8">
                <CategoryForm :form="form" :parents="parents" :groups="groups" submit-label="Yadda saxla" @submit="submit" />
            </div>
        </div>
    </AuthenticatedLayout>
</template>

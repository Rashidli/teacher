<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import AuthLayout from '@/Layouts/AuthLayout.vue';
import TextField from '@/Components/Form/TextField.vue';
import SubmitButton from '@/Components/Form/SubmitButton.vue';
import FormAlert from '@/Components/Form/FormAlert.vue';
import { useLocale } from '@/Composables/useLocale';

defineProps({
    status: { type: String },
});

const { lroute } = useLocale();

const form = useForm({
    email: '',
});

const submit = () => {
    form.post(lroute('password.email'));
};
</script>

<template>
    <AuthLayout
        :title="$t('auth_pages.forgot.title')"
        :label="$t('auth_pages.forgot.card_label')"
        :heading="$t('auth_pages.forgot.heading')"
        :lead="$t('auth_pages.forgot.lead')"
    >
        <template #status>
            <FormAlert v-if="status">{{ status }}</FormAlert>
        </template>

        <form class="form" novalidate @submit.prevent="submit">
            <TextField
                id="email"
                v-model="form.email"
                type="email"
                :label="$t('auth_pages.fields.email')"
                autocomplete="username"
                inputmode="email"
                :error="form.errors.email"
                required
            />

            <SubmitButton :processing="form.processing">{{ $t('auth_pages.forgot.submit') }}</SubmitButton>
        </form>

        <template #after>
            <Link :href="lroute('login')" class="text-link">{{ $t('auth_pages.forgot.back') }}</Link>
        </template>
    </AuthLayout>
</template>

<style scoped>
.form {
    display: grid;
    grid-template-columns: minmax(0, 1fr);
    gap: 18px;
    margin-top: 20px;
}

.text-link {
    display: inline-flex;
    align-items: center;
    min-height: 44px;
    color: var(--pen);
    font-weight: 600;
    text-decoration: underline;
    text-decoration-thickness: 1.5px;
    text-underline-offset: 4px;
}
</style>

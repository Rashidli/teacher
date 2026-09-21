<script setup>
import { useForm } from '@inertiajs/vue3';
import AuthLayout from '@/Layouts/AuthLayout.vue';
import TextField from '@/Components/Form/TextField.vue';
import PasswordField from '@/Components/Form/PasswordField.vue';
import SubmitButton from '@/Components/Form/SubmitButton.vue';
import { useLocale } from '@/Composables/useLocale';

const props = defineProps({
    email: { type: String, required: true },
    token: { type: String, required: true },
});

const { lroute } = useLocale();

const form = useForm({
    token: props.token,
    email: props.email,
    password: '',
    password_confirmation: '',
});

const submit = () => {
    form.post(lroute('password.store'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <AuthLayout
        :title="$t('auth_pages.reset.title')"
        :label="$t('auth_pages.reset.card_label')"
        :heading="$t('auth_pages.reset.heading')"
        :lead="$t('auth_pages.reset.lead')"
    >
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

            <PasswordField
                id="password"
                v-model="form.password"
                :label="$t('auth_pages.fields.new_password')"
                autocomplete="new-password"
                :hint="$t('auth_pages.register.password_hint')"
                :error="form.errors.password"
                required
            />

            <PasswordField
                id="password_confirmation"
                v-model="form.password_confirmation"
                :label="$t('auth_pages.fields.password_confirmation')"
                autocomplete="new-password"
                :error="form.errors.password_confirmation"
                required
            />

            <SubmitButton :processing="form.processing">{{ $t('auth_pages.reset.submit') }}</SubmitButton>
        </form>
    </AuthLayout>
</template>

<style scoped>
.form {
    display: grid;
    grid-template-columns: minmax(0, 1fr);
    gap: 18px;
    margin-top: 20px;
}
</style>

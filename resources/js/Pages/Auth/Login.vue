<script setup>
import { computed } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import AuthLayout from '@/Layouts/AuthLayout.vue';
import TextField from '@/Components/Form/TextField.vue';
import PasswordField from '@/Components/Form/PasswordField.vue';
import CheckboxField from '@/Components/Form/CheckboxField.vue';
import SubmitButton from '@/Components/Form/SubmitButton.vue';
import FormAlert from '@/Components/Form/FormAlert.vue';
import { useLocale } from '@/Composables/useLocale';

defineProps({
    canResetPassword: { type: Boolean },
    status: { type: String },
});

const { lroute } = useLocale();

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const hasErrors = computed(() => Object.keys(form.errors).length > 0);

const submit = () => {
    form.post(lroute('login'), {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <AuthLayout
        :title="$t('auth_pages.login.title')"
        :label="$t('auth_pages.login.card_label')"
        :heading="$t('auth_pages.login.heading')"
        :lead="$t('auth_pages.login.lead')"
    >
        <template #status>
            <FormAlert v-if="status">{{ status }}</FormAlert>
            <FormAlert v-if="hasErrors" tone="error">{{ $t('auth_pages.errors_summary') }}</FormAlert>
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

            <PasswordField
                id="password"
                v-model="form.password"
                :label="$t('auth_pages.fields.password')"
                autocomplete="current-password"
                :error="form.errors.password"
                required
            />

            <div class="form-row">
                <CheckboxField id="remember" v-model="form.remember">
                    {{ $t('auth_pages.fields.remember') }}
                </CheckboxField>
                <Link v-if="canResetPassword" :href="lroute('password.request')" class="text-link">
                    {{ $t('auth_pages.login.forgot') }}
                </Link>
            </div>

            <SubmitButton :processing="form.processing">{{ $t('auth_pages.login.submit') }}</SubmitButton>
        </form>

        <template #after>
            <p class="switch">
                {{ $t('auth_pages.login.no_account') }}
                <Link :href="lroute('register')" class="text-link">{{ $t('auth_pages.login.register_link') }}</Link>
            </p>
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

/* "Məni xatırla" və "Parolu unutmusan?": dar ekranda alt-alta */
.form-row {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 0 16px;
    margin-block: -8px -4px;
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

.switch {
    margin: 0;
    color: var(--muted);
}
</style>

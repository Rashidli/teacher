<script setup>
import { computed } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import AuthLayout from '@/Layouts/AuthLayout.vue';
import TextField from '@/Components/Form/TextField.vue';
import PasswordField from '@/Components/Form/PasswordField.vue';
import PhoneField from '@/Components/Form/PhoneField.vue';
import CheckboxField from '@/Components/Form/CheckboxField.vue';
import RadioGroupField from '@/Components/Form/RadioGroupField.vue';
import SubmitButton from '@/Components/Form/SubmitButton.vue';
import FormAlert from '@/Components/Form/FormAlert.vue';
import { useLocale } from '@/Composables/useLocale';

// Şagird qeydiyyatı: bütün yeni hesablar "student" rolunu alır (RegisteredUserController).
const props = defineProps({
    operatorCodes: { type: Array, default: () => ['10', '50', '51', '55', '60', '70', '77', '99'] },
    // Interfeys dilinə (və ya kataloqdakı seçimə) görə öncədən seçilir, dəyişdirilə bilər
    defaultSector: { type: String, default: 'az' },
});

const { lroute } = useLocale();

const form = useForm({
    first_name: '',
    last_name: '',
    email: '',
    phone: '', // 9 rəqəm; göndəriləndə +994 əlavə olunur
    password: '',
    password_confirmation: '',
    sector: props.defaultSector,
    terms: false,
});

const hasErrors = computed(() => Object.keys(form.errors).length > 0);

// ":link ilə razıyam" / "Принимаю :link": link mətni cümlənin içində, yeri dilə görə dəyişir.
// $t şablonda istifadə olunur (SSR-də hər sorğunun öz i18n nüsxəsi var).
const splitAroundLink = (text) => {
    const [before = '', after = ''] = String(text).split(':link');

    return { before, after };
};

const submit = () => {
    form
        .transform((data) => ({
            ...data,
            phone: data.phone ? `+994${data.phone}` : '',
        }))
        .post(lroute('register'), {
            onFinish: () => form.reset('password', 'password_confirmation'),
        });
};
</script>

<template>
    <AuthLayout
        :title="$t('auth_pages.register.title')"
        :label="$t('auth_pages.register.card_label')"
        :heading="$t('auth_pages.register.heading')"
        :lead="$t('auth_pages.register.lead')"
        wide
    >
        <template #status>
            <FormAlert v-if="hasErrors" tone="error">{{ $t('auth_pages.errors_summary') }}</FormAlert>
        </template>

        <form class="form" novalidate @submit.prevent="submit">
            <div class="name-row">
                <TextField
                    id="first_name"
                    v-model="form.first_name"
                    :label="$t('auth_pages.fields.first_name')"
                    autocomplete="given-name"
                    :error="form.errors.first_name"
                    required
                />
                <TextField
                    id="last_name"
                    v-model="form.last_name"
                    :label="$t('auth_pages.fields.last_name')"
                    autocomplete="family-name"
                    :error="form.errors.last_name"
                    required
                />
            </div>

            <TextField
                id="email"
                v-model="form.email"
                type="email"
                :label="$t('auth_pages.fields.email')"
                autocomplete="email"
                inputmode="email"
                :error="form.errors.email"
                required
            />

            <PhoneField
                id="phone"
                v-model="form.phone"
                :label="$t('auth_pages.fields.phone')"
                :operator-codes="props.operatorCodes"
                :hint="$t('auth_pages.register.phone_hint')"
                :invalid-message="$t('auth_pages.register.phone_invalid')"
                :error="form.errors.phone"
                required
            />

            <PasswordField
                id="password"
                v-model="form.password"
                :label="$t('auth_pages.fields.password')"
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

            <RadioGroupField
                id="sector"
                v-model="form.sector"
                :legend="$t('auth_pages.register.sector_label')"
                :hint="$t('auth_pages.register.sector_hint')"
                :options="[
                    { value: 'az', label: $t('auth_pages.register.sector_az') },
                    { value: 'ru', label: $t('auth_pages.register.sector_ru') },
                ]"
                :error="form.errors.sector"
            />

            <CheckboxField id="terms" v-model="form.terms" :error="form.errors.terms" required>
                {{ splitAroundLink($t('auth_pages.register.terms_label')).before }}<a
                    :href="lroute('terms')"
                    target="_blank"
                    rel="noopener"
                    class="inline-link"
                >{{ $t('auth_pages.register.terms_link') }}<span class="visually-hidden"> {{ $t('auth_pages.register.terms_new_tab') }}</span></a>{{ splitAroundLink($t('auth_pages.register.terms_label')).after }}
            </CheckboxField>

            <SubmitButton :processing="form.processing">{{ $t('auth_pages.register.submit') }}</SubmitButton>
        </form>

        <template #after>
            <p class="switch">
                {{ $t('auth_pages.register.have_account') }}
                <Link :href="lroute('login')" class="text-link">{{ $t('auth_pages.register.login_link') }}</Link>
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

/* Ad və Soyad: telefonda alt-alta, 560px-dən yan-yana */
.name-row {
    display: grid;
    grid-template-columns: minmax(0, 1fr);
    gap: 18px;
}

.inline-link {
    color: var(--pen);
    font-weight: 600;
    text-decoration: underline;
    text-decoration-thickness: 1.5px;
    text-underline-offset: 3px;
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

@media (min-width: 560px) {
    .name-row {
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 16px;
    }
}
</style>

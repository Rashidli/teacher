<script setup>
import { ref } from 'vue';
import FieldError from './FieldError.vue';

const model = defineModel({ type: String, default: '' });

defineProps({
    id: { type: String, required: true },
    label: { type: String, required: true },
    autocomplete: { type: String, default: 'current-password' },
    error: { type: String, default: '' },
    hint: { type: String, default: '' },
    required: { type: Boolean, default: false },
});

const visible = ref(false);
</script>

<template>
    <div class="field">
        <label :for="id" class="field-label">{{ label }}</label>
        <div class="password" :class="{ 'password--error': error }">
            <input
                :id="id"
                v-model="model"
                :type="visible ? 'text' : 'password'"
                :name="id"
                :autocomplete="autocomplete"
                :required="required"
                autocapitalize="off"
                spellcheck="false"
                class="password-input"
                :aria-invalid="error ? 'true' : undefined"
                :aria-describedby="[hint ? `${id}-hint` : null, error ? `${id}-error` : null].filter(Boolean).join(' ') || undefined"
            />
            <!-- Göstər/gizlət: 44×44 toxunma sahəsi, vəziyyət aria-pressed ilə -->
            <button
                type="button"
                class="password-toggle"
                :aria-pressed="visible ? 'true' : 'false'"
                :aria-controls="id"
                :aria-label="visible ? $t('auth_pages.password_hide') : $t('auth_pages.password_show')"
                :title="visible ? $t('auth_pages.password_hide') : $t('auth_pages.password_show')"
                @click="visible = !visible"
            >
                <svg v-if="!visible" viewBox="0 0 24 24" aria-hidden="true"><path d="M2 12s3.6-6.5 10-6.5S22 12 22 12s-3.6 6.5-10 6.5S2 12 2 12Z" fill="none" stroke="currentColor" stroke-width="1.8" /><circle cx="12" cy="12" r="3" fill="none" stroke="currentColor" stroke-width="1.8" /></svg>
                <svg v-else viewBox="0 0 24 24" aria-hidden="true"><path d="M3 3l18 18M10.6 5.6A10.8 10.8 0 0 1 12 5.5C18.4 5.5 22 12 22 12a17 17 0 0 1-3.2 3.9M6.4 6.9A16.6 16.6 0 0 0 2 12s3.6 6.5 10 6.5c1.7 0 3.2-.5 4.5-1.1M9.9 9.9a3 3 0 0 0 4.2 4.2" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" /></svg>
            </button>
        </div>
        <p v-if="hint" :id="`${id}-hint`" class="field-hint">{{ hint }}</p>
        <FieldError :id="`${id}-error`" :message="error" />
    </div>
</template>

<style scoped>
.field-label {
    display: block;
    margin-bottom: 6px;
    font-size: 1rem;
    font-weight: 600;
}

.password {
    display: flex;
    align-items: stretch;
    min-height: 48px;
    border: 1.5px solid #C7CBD1;
    border-radius: 6px;
    background: #fff;
}

.password:hover {
    border-color: #9EA4AC;
}

.password:focus-within {
    border-color: var(--pen);
    outline: 2px solid var(--pen);
    outline-offset: 1px;
}

.password--error,
.password--error:hover {
    border-color: var(--ink-red);
}

.password-input {
    flex: 1;
    min-width: 0;
    padding: 10px 12px;
    border: 0;
    border-radius: 6px 0 0 6px;
    background: transparent;
    color: var(--graphite);
    font: inherit;
    font-size: 1rem;
}

.password-input:focus {
    outline: none;
    box-shadow: none;
}

.password-toggle {
    display: grid;
    place-items: center;
    flex: none;
    width: 48px;
    min-height: 44px;
    border: 0;
    border-left: 1px solid #E3E5E8;
    border-radius: 0 6px 6px 0;
    background: transparent;
    color: var(--muted);
    cursor: pointer;
}

.password-toggle:hover {
    color: var(--pen);
}

.password-toggle svg {
    width: 22px;
    height: 22px;
}

.field-hint {
    margin: 6px 0 0;
    color: var(--muted);
    font-size: 0.9375rem;
    line-height: 1.4;
}
</style>

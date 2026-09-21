<script setup>
import FieldError from './FieldError.vue';

const model = defineModel({ type: String, default: '' });

defineProps({
    id: { type: String, required: true },
    label: { type: String, required: true },
    type: { type: String, default: 'text' },
    autocomplete: { type: String, default: undefined },
    inputmode: { type: String, default: undefined },
    error: { type: String, default: '' },
    hint: { type: String, default: '' },
    required: { type: Boolean, default: false },
    autofocus: { type: Boolean, default: false },
});
</script>

<template>
    <div class="field">
        <label :for="id" class="field-label">{{ label }}</label>
        <input
            :id="id"
            v-model="model"
            :type="type"
            :name="id"
            :autocomplete="autocomplete"
            :inputmode="inputmode"
            :required="required"
            :autofocus="autofocus"
            class="field-input"
            :class="{ 'field-input--error': error }"
            :aria-invalid="error ? 'true' : undefined"
            :aria-describedby="[hint ? `${id}-hint` : null, error ? `${id}-error` : null].filter(Boolean).join(' ') || undefined"
        />
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

/* 16px şrift: iOS fokusda zoom etmir */
.field-input {
    display: block;
    width: 100%;
    min-height: 48px;
    padding: 10px 12px;
    border: 1.5px solid #C7CBD1;
    border-radius: 6px;
    background: #fff;
    color: var(--graphite);
    font: inherit;
    font-size: 1rem;
    line-height: 1.4;
}

.field-input:hover {
    border-color: #9EA4AC;
}

.field-input:focus {
    border-color: var(--pen);
    outline: 2px solid var(--pen);
    outline-offset: 1px;
    box-shadow: none;
}

.field-input--error,
.field-input--error:hover {
    border-color: var(--ink-red);
}

.field-hint {
    margin: 6px 0 0;
    color: var(--muted);
    font-size: 0.9375rem;
    line-height: 1.4;
}
</style>

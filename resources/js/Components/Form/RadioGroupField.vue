<script setup>
import FieldError from './FieldError.vue';

// İki-üç variantlı seçim (məs. tədris sektoru). Hər variant ayrıca toxunma sahəsidir.
const model = defineModel({ type: String, default: '' });

defineProps({
    id: { type: String, required: true },
    legend: { type: String, required: true },
    // [{ value, label }]
    options: { type: Array, required: true },
    hint: { type: String, default: '' },
    error: { type: String, default: '' },
});
</script>

<template>
    <fieldset class="field" :aria-describedby="error ? `${id}-error` : (hint ? `${id}-hint` : undefined)">
        <legend class="legend">{{ legend }}</legend>
        <p v-if="hint" :id="`${id}-hint`" class="hint">{{ hint }}</p>

        <div class="options" :class="{ 'options--error': error }">
            <label v-for="option in options" :key="option.value" class="option" :class="{ 'option--on': model === option.value }">
                <input
                    :id="`${id}-${option.value}`"
                    v-model="model"
                    type="radio"
                    :name="id"
                    :value="option.value"
                    :aria-invalid="error ? 'true' : undefined"
                />
                <span>{{ option.label }}</span>
            </label>
        </div>

        <FieldError :id="`${id}-error`" :message="error" />
    </fieldset>
</template>

<style scoped>
.field {
    margin: 0;
    padding: 0;
    border: 0;
}

.legend {
    padding: 0;
    font-size: 0.9375rem;
    font-weight: 600;
}

.hint {
    margin: 4px 0 0;
    color: var(--muted);
    font-size: 0.9375rem;
    line-height: 1.4;
}

.options {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin-top: 10px;
}

.option {
    display: flex;
    align-items: center;
    gap: 10px;
    min-height: 44px;
    padding: 8px 14px;
    border: 1.5px solid rgba(22, 19, 14, 0.25);
    border-radius: 10px;
    cursor: pointer;
}

.option--on {
    border-color: var(--graphite);
}

.options--error .option {
    border-color: var(--ink-red);
}

.option input {
    width: 20px;
    height: 20px;
    margin: 0;
    accent-color: var(--graphite);
    cursor: pointer;
}

.option input:focus-visible {
    outline: 2px solid var(--pen);
    outline-offset: 2px;
}
</style>

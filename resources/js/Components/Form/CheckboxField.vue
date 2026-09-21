<script setup>
import FieldError from './FieldError.vue';

// Bütün sətir (qutu + mətn) toxunma sahəsidir. Mətn slot ilə verilir (içində link ola bilər).
const model = defineModel({ type: Boolean, default: false });

defineProps({
    id: { type: String, required: true },
    error: { type: String, default: '' },
    required: { type: Boolean, default: false },
});
</script>

<template>
    <div class="field">
        <div class="check" :class="{ 'check--error': error }">
            <input
                :id="id"
                v-model="model"
                type="checkbox"
                :name="id"
                :required="required"
                class="check-box"
                :aria-invalid="error ? 'true' : undefined"
                :aria-describedby="error ? `${id}-error` : undefined"
            />
            <label :for="id" class="check-label"><slot /></label>
        </div>
        <FieldError :id="`${id}-error`" :message="error" />
    </div>
</template>

<style scoped>
.check {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    min-height: 44px;
    padding-block: 10px;
}

/* Cavab kartının xanası kimi: seçiləndə qrafitlə doldurulur */
.check-box {
    flex: none;
    width: 24px;
    height: 24px;
    margin: 0;
    border: 1.5px solid var(--ink-red);
    border-radius: 4px;
    background-color: #fff;
    color: var(--graphite);
    cursor: pointer;
}

.check-box:checked {
    border-color: var(--graphite);
    background-color: var(--graphite);
}

.check-box:focus {
    box-shadow: none;
}

.check-box:focus-visible {
    outline: 2px solid var(--pen);
    outline-offset: 2px;
}

.check--error .check-box {
    border-color: var(--ink-red);
    border-width: 2px;
}

.check-label {
    font-size: 1rem;
    line-height: 1.5;
    cursor: pointer;
}
</style>

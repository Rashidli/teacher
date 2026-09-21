<script setup>
// Sahənin altında xəta: rəng + simvol + mətn (yalnız rəngə güvənilmir)
defineProps({
    id: { type: String, required: true },
    message: { type: String, default: '' },
});
</script>

<template>
    <p v-if="message" :id="id" class="field-error">
        <svg viewBox="0 0 16 16" aria-hidden="true"><circle cx="8" cy="8" r="7" fill="currentColor" /><path d="M8 4.5v4.2M8 11.2v.3" stroke="#fff" stroke-width="1.8" stroke-linecap="round" /></svg>
        <span>{{ message }}</span>
    </p>
</template>

<style scoped>
.field-error {
    display: flex;
    gap: 6px;
    align-items: flex-start;
    margin: 6px 0 0;
    color: var(--ink-red);
    font-size: 0.9375rem;
    line-height: 1.4;
}

.field-error svg {
    flex: none;
    width: 16px;
    height: 16px;
    margin-top: 2px;
}
</style>

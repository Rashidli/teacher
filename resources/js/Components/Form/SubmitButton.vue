<script setup>
defineProps({
    processing: { type: Boolean, default: false },
});
</script>

<template>
    <button type="submit" class="submit" :disabled="processing" :aria-busy="processing ? 'true' : 'false'">
        <slot />
    </button>
</template>

<style scoped>
.submit {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 100%;
    min-height: 48px;
    padding: 10px 20px;
    border: 0;
    border-radius: 6px;
    background: var(--pen);
    color: #fff;
    font: inherit;
    font-size: 1rem;
    font-weight: 600;
    cursor: pointer;
}

.submit:hover {
    background: var(--pen-deep);
}

.submit:disabled {
    opacity: 0.65;
    cursor: progress;
}
</style>

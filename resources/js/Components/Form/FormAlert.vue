<script setup>
// Formanın yuxarısında bildiriş: "success" (məs. link göndərildi) və ya "error" (xəta xülasəsi)
defineProps({
    tone: { type: String, default: 'success' },
});
</script>

<template>
    <p class="alert" :class="`alert--${tone}`" :role="tone === 'error' ? 'alert' : 'status'">
        <slot />
    </p>
</template>

<style scoped>
.alert {
    margin: 16px 0 0;
    padding: 12px 14px;
    border-left: 4px solid currentColor;
    border-radius: 4px;
    font-size: 1rem;
    line-height: 1.45;
}

.alert--success {
    background: #EDF6F0;
    color: var(--correct);
}

.alert--error {
    background: #FBEEF0;
    color: var(--ink-red);
}
</style>

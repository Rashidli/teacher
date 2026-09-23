<script setup>
/**
 * Panel səhifəsinin başlığı — `AuthenticatedLayout`-un `#header` yuvası üçün.
 *
 * Tipoqrafiya ictimai tərəflə eynidir: başlıq `--font-display`, alt yazı `--muted`.
 * Sağ tərəfdəki əməliyyat düyməsi `actions` yuvasına qoyulur.
 */
defineProps({
    title: { type: String, required: true },
    lead: { type: String, default: null },
});
</script>

<template>
    <div class="head">
        <div class="head-text">
            <h1 class="head-title">{{ title }}</h1>
            <p v-if="lead" class="head-lead">{{ lead }}</p>
        </div>
        <div v-if="$slots.actions" class="head-actions">
            <slot name="actions" />
        </div>
    </div>
</template>

<style scoped>
.head {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 12px 20px;
}

.head-text {
    min-width: 0;
}

.head-title {
    margin: 0;
    font-family: var(--font-display);
    font-size: 1.5rem;
    font-weight: 700;
    letter-spacing: -0.01em;
    color: var(--graphite);
}

.head-lead {
    margin: 4px 0 0;
    font-size: 0.9375rem;
    color: var(--muted);
}

.head-actions {
    flex: none;
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

@media (min-width: 1024px) {
    .head-title { font-size: 1.75rem; }
}
</style>

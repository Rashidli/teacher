<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';

/**
 * Panel düyməsi — ictimai tərəfdəki düymə ilə eyni formada (dolu qrafit, 999px radius).
 *
 * `href` verilsə Inertia `<Link>`, verilməsə `<button>` render olunur. Toxunma sahəsi
 * mobildə də 44px-dən kiçik olmur.
 */
const props = defineProps({
    href: { type: String, default: null },
    // 'primary' — əsas əməliyyat, 'ghost' — ikinci dərəcəli, 'quiet' — mətn linki
    variant: { type: String, default: 'primary' },
    small: { type: Boolean, default: false },
    disabled: { type: Boolean, default: false },
});

const classes = computed(() => ['btn', `btn--${props.variant}`, props.small ? 'btn--small' : '']);
</script>

<template>
    <Link v-if="href" :href="href" :class="classes">
        <slot />
    </Link>
    <button v-else type="button" :class="classes" :disabled="disabled">
        <slot />
    </button>
</template>

<style scoped>
.btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    /* Toxunma sahəsi 44px: mətn ölçüsü deyil, padding böyüyür */
    min-height: 44px;
    padding: 10px 20px;
    border: 1.5px solid transparent;
    border-radius: 999px;
    font: inherit;
    font-size: 0.95rem;
    font-weight: 600;
    line-height: 1.2;
    text-align: center;
    text-decoration: none;
    cursor: pointer;
    transition: background-color 150ms ease, border-color 150ms ease, color 150ms ease;
}

.btn--primary {
    background: var(--graphite);
    border-color: var(--graphite);
    color: var(--paper);
}

.btn--primary:hover {
    background: var(--pen-deep);
    border-color: var(--pen-deep);
}

.btn--ghost {
    background: none;
    border-color: rgba(22, 19, 14, 0.25);
    color: var(--graphite);
}

.btn--ghost:hover {
    border-color: var(--graphite);
}

.btn--quiet {
    min-height: 44px;
    padding-inline: 4px;
    background: none;
    color: var(--pen);
    text-decoration: underline;
    text-underline-offset: 4px;
}

.btn--small {
    min-height: 44px;
    padding: 8px 14px;
    font-size: 0.9rem;
}

.btn:disabled {
    opacity: 0.6;
    cursor: progress;
}
</style>

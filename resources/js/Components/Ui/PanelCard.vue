<script setup>
/**
 * Panel kartı — ictimai kataloq kartları ilə EYNİ sistemdə: eyni radius, kənar, kölgə,
 * boşluq və rəng tokenləri (`:root`-dan). Tailwind-in `bg-white shadow-sm rounded-lg`
 * defoltlarını əvəz edir.
 *
 * `accent` verilsə kartın üstündə rəngli zolaq olur — kataloq kartındakı kateqoriya
 * zolağının eynisi. Rəng TƏK göstərici deyil: başlıq mətni onsuz da var.
 */
defineProps({
    title: { type: String, default: null },
    accent: { type: String, default: null },
    // Kartın içi öz boşluğunu idarə edirsə (məs. siyahı) padding söndürülür
    flush: { type: Boolean, default: false },
});
</script>

<template>
    <section class="card" :style="accent ? { '--accent': accent } : null">
        <span v-if="accent" class="card-bar" aria-hidden="true"></span>

        <header v-if="title || $slots.actions" class="card-head">
            <h2 v-if="title" class="card-title">{{ title }}</h2>
            <div v-if="$slots.actions" class="card-actions">
                <slot name="actions" />
            </div>
        </header>

        <div :class="flush ? 'card-body card-body--flush' : 'card-body'">
            <slot />
        </div>
    </section>
</template>

<style scoped>
.card {
    position: relative;
    display: flex;
    flex-direction: column;
    min-width: 0;
    border: var(--card-border);
    border-radius: var(--card-radius);
    background: var(--paper);
}

.card-bar {
    position: absolute;
    inset: 0 0 auto;
    height: 4px;
    border-radius: var(--card-radius) var(--card-radius) 0 0;
    background: var(--accent);
}

.card-head {
    display: flex;
    flex-wrap: wrap;
    align-items: baseline;
    justify-content: space-between;
    gap: 8px 16px;
    padding: 18px 18px 0;
}

.card-title {
    margin: 0;
    font-family: var(--font-display);
    font-size: 1.15rem;
    font-weight: 600;
    color: var(--graphite);
}

.card-actions {
    flex: none;
    font-size: 0.9375rem;
}

.card-body {
    padding: 14px 18px 18px;
}

.card-body--flush {
    padding: 14px 0 0;
}
</style>

<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';

/**
 * Panel siyahısının bir sətri — şagird panelindəki bütün siyahılar bu formadadır.
 *
 * Sol kənarda imtahanın BÖLMƏ RƏNGİ durur (kataloq kartındakı zolağın sətir variantı),
 * üstündə bölmə yolu, sonra başlıq və meta məlumat, sağda isə əməliyyat və ya bal.
 *
 * `href` verilsə bütün sətir linkdir; verilməsə adi blokdur və əməliyyat `actions`
 * yuvasındakı düymədədir (iç-içə interaktiv element olmasın deyə).
 */
const props = defineProps({
    title: { type: String, required: true },
    href: { type: String, default: null },
    // { root, leaf, color } — `Category::trail()`
    trail: { type: Object, default: null },
});

const accent = computed(() => props.trail?.color || 'var(--muted)');

const trailText = computed(
    () => [props.trail?.root, props.trail?.leaf].filter(Boolean).join(' › '),
);
</script>

<template>
    <li class="row-item" :style="{ '--accent': accent }">
        <component
            :is="href ? Link : 'div'"
            :href="href || undefined"
            :class="['row', href ? 'row--link' : '']"
        >
            <div class="row-text">
                <p v-if="trailText" class="row-trail">
                    <span class="row-dot" aria-hidden="true"></span>{{ trailText }}
                </p>
                <p class="row-title">{{ title }}</p>
                <p v-if="$slots.meta" class="row-meta"><slot name="meta" /></p>
            </div>

            <div v-if="$slots.actions" class="row-side">
                <slot name="actions" />
            </div>
        </component>
    </li>
</template>

<style scoped>
.row-item + .row-item {
    border-top: 1px dashed var(--ink-red-line);
}

.row {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 10px 16px;
    /* Bölmə rəngi sol kənarda */
    border-left: 3px solid var(--accent);
    padding: 14px 18px;
    color: inherit;
    text-decoration: none;
}

.row--link:hover {
    background: var(--paper-sunk);
}

.row-text {
    min-width: 0;
}

.row-trail {
    display: flex;
    align-items: center;
    gap: 6px;
    margin: 0 0 2px;
    font-size: 0.8125rem;
    font-weight: 600;
    color: var(--accent);
}

.row-dot {
    width: 7px;
    height: 7px;
    flex: none;
    border-radius: 50%;
    background: var(--accent);
}

.row-title {
    margin: 0;
    font-weight: 600;
    color: var(--graphite);
}

.row-meta {
    margin: 2px 0 0;
    font-size: 0.875rem;
    color: var(--muted);
}

.row-side {
    flex: none;
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 8px;
    text-align: right;
}
</style>

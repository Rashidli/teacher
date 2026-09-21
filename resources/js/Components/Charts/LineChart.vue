<script setup>
import { computed } from 'vue';

/**
 * Sadə SVG xətt qrafiki (kitabxanasız): cəhdlərin zaman üzrə dəyişməsi.
 * Nöqtələr [{ label, value }] şəklində gəlir; dəyər 0–max aralığındadır.
 */
const props = defineProps({
    points: { type: Array, default: () => [] },
    max: { type: Number, default: 100 },
    height: { type: Number, default: 160 },
    label: { type: String, default: '' },
});

const WIDTH = 600;
const PADDING = { top: 12, right: 12, bottom: 28, left: 34 };

const usable = computed(() => ({
    width: WIDTH - PADDING.left - PADDING.right,
    height: props.height - PADDING.top - PADDING.bottom,
}));

const coords = computed(() => {
    const count = props.points.length;

    return props.points.map((point, index) => {
        const ratio = count > 1 ? index / (count - 1) : 0.5;
        const value = Math.max(0, Math.min(Number(point.value) || 0, props.max));

        return {
            ...point,
            x: PADDING.left + ratio * usable.value.width,
            y: PADDING.top + usable.value.height * (1 - value / props.max),
        };
    });
});

const path = computed(() => coords.value.map((c, i) => `${i === 0 ? 'M' : 'L'}${c.x.toFixed(1)},${c.y.toFixed(1)}`).join(' '));

// Y oxu: 0, yarı və maksimum
const gridLines = computed(() => [0, props.max / 2, props.max].map((value) => ({
    value: Math.round(value),
    y: PADDING.top + usable.value.height * (1 - value / props.max),
})));
</script>

<template>
    <figure class="m-0">
        <figcaption v-if="label" class="mb-2 text-sm text-gray-600">{{ label }}</figcaption>

        <svg :viewBox="`0 0 ${WIDTH} ${height}`" class="w-full" role="img" :aria-label="label">
            <g>
                <line
                    v-for="line in gridLines"
                    :key="line.value"
                    :x1="PADDING.left"
                    :x2="WIDTH - PADDING.right"
                    :y1="line.y"
                    :y2="line.y"
                    stroke="currentColor"
                    class="text-gray-200"
                />
                <text
                    v-for="line in gridLines"
                    :key="`t-${line.value}`"
                    :x="PADDING.left - 6"
                    :y="line.y + 4"
                    text-anchor="end"
                    class="fill-gray-400 text-[10px]"
                >{{ line.value }}</text>
            </g>

            <path :d="path" fill="none" stroke="currentColor" stroke-width="2" class="text-indigo-600" />

            <g v-for="(point, index) in coords" :key="index">
                <circle :cx="point.x" :cy="point.y" r="4" class="fill-indigo-600" />
                <title>{{ point.label }}: {{ point.value }}</title>
            </g>

            <text
                v-for="(point, index) in coords"
                :key="`l-${index}`"
                :x="point.x"
                :y="height - 8"
                text-anchor="middle"
                class="fill-gray-500 text-[10px]"
            >{{ index === 0 || index === coords.length - 1 ? point.label : '' }}</text>
        </svg>

        <p v-if="!points.length" class="text-sm text-gray-500">Hələ məlumat yoxdur.</p>
    </figure>
</template>

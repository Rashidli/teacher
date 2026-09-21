<script setup>
import { computed } from 'vue';

/** Faiz zolağı: 0–100 arası dəyər. Rəng yalnız köməkçidir, rəqəm həmişə yazılır. */
const props = defineProps({
    value: { type: [Number, String], default: 0 },
    max: { type: Number, default: 100 },
    weak: { type: Boolean, default: false },
});

const percent = computed(() => {
    const value = Number(props.value) || 0;

    return Math.max(0, Math.min(100, (value / props.max) * 100));
});
</script>

<template>
    <div class="flex items-center gap-2">
        <div class="h-2 w-full rounded-full bg-gray-100" role="presentation">
            <div
                class="h-2 rounded-full"
                :class="weak ? 'bg-red-500' : 'bg-indigo-600'"
                :style="{ width: `${percent}%` }"
            ></div>
        </div>
        <span class="w-14 shrink-0 text-right text-sm tabular-nums text-gray-700">{{ value ?? 0 }}%</span>
    </div>
</template>

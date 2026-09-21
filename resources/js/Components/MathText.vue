<script setup>
import { computed } from 'vue';
import katex from 'katex';
import 'katex/dist/katex.min.css';

const props = defineProps({
    text: { type: String, default: '' },
});

const rendered = computed(() => {
    if (!props.text) return '';

    // $...$ inline və $$...$$ block formulaları render et
    return props.text
        .replace(/\$\$([^$]+)\$\$/g, (_, formula) => {
            try {
                return katex.renderToString(formula.trim(), { displayMode: true, throwOnError: false });
            } catch {
                return `<span class="text-red-500">[Formula xətası: ${formula}]</span>`;
            }
        })
        .replace(/\$([^$\n]+)\$/g, (_, formula) => {
            try {
                return katex.renderToString(formula.trim(), { displayMode: false, throwOnError: false });
            } catch {
                return `<span class="text-red-500">[Formula xətası: ${formula}]</span>`;
            }
        });
});
</script>

<template>
    <span v-html="rendered" class="math-text" />
</template>

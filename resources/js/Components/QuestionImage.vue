<script setup>
import { computed } from 'vue';

/**
 * Sual və ya variant şəkli.
 *
 * Şəkilli sualda (yol nişanı, sxem, qrafik) şəkil məzmunun ÖZÜDÜR, ona görə `alt` mətni
 * mütləqdir. Verilməyibsə ümumi mətnə düşülür — ekran oxuyucusu heç olmasa şəklin
 * varlığını bildirsin.
 *
 * Mobildə responsivdir: konteynerdən enli olmur, hündürlüyü nisbəti saxlayır.
 */
const props = defineProps({
    /*
     * HAZIR URL. Yol birləşdirilməsi burada EDİLMİR: URL backend-də
     * `Storage::disk('public')->url()` ilə qurulur (bax `Question::imageUrl()`),
     * beləliklə disk konfiqurasiyası və ya CDN dəyişəndə şablonlar sınmır.
     */
    url: { type: String, default: null },
    alt: { type: String, default: null },
    // 'question' — böyük, 'option' — variant sətrindəki kiçik şəkil
    size: { type: String, default: 'question' },
});

const src = computed(() => props.url || null);
</script>

<template>
    <img
        v-if="src"
        :src="src"
        :alt="alt || 'Sual şəkli'"
        loading="lazy"
        decoding="async"
        class="question-image"
        :class="`question-image--${size}`"
    />
</template>

<style scoped>
.question-image {
    display: block;
    max-width: 100%;
    height: auto;
    border: 1px solid rgba(22, 19, 14, 0.15);
    border-radius: 10px;
    background: #fff;
}

.question-image--question {
    margin-top: 12px;
    /* Yol nişanı kimi kiçik şəkil böyüdülməsin, geniş sxem isə ekrana sığsın */
    max-height: 320px;
}

.question-image--option {
    max-width: 120px;
    max-height: 90px;
}
</style>

<script setup>
import { computed } from 'vue';
import { Head, usePage } from '@inertiajs/vue3';

/**
 * İctimai səhifələrin <head> teqləri: title, description, robots, canonical və hreflang.
 * SSR işləyəndə serverdə render olunur (@inertiaHead). SSR serveri əlçatmaz olanda
 * eyni teqləri app.blade.php yazır (resources/views/partials/seo.blade.php).
 */
const props = defineProps({
    title: { type: String, default: '' },
    description: { type: String, default: '' },
    noindex: { type: Boolean, default: false },
});

const page = usePage();
const seo = computed(() => page.props.seo);
// SEO_INDEXING bağlıdırsa (müvəqqəti domen, staging) bütün səhifələr bağlanır
const robots = computed(() => (props.noindex || page.props.indexable === false ? 'noindex, nofollow' : null));
</script>

<template>
    <Head :title="title">
        <meta head-key="description" name="description" :content="description || $t('site.seo.description')" />
        <meta v-if="robots" head-key="robots" name="robots" :content="robots" />
        <template v-if="seo">
            <link head-key="canonical" rel="canonical" :href="seo.canonical" />
            <link
                v-for="(url, lang) in seo.alternates"
                :key="lang"
                :head-key="`hreflang-${lang}`"
                rel="alternate"
                :hreflang="lang"
                :href="url"
            />
            <link
                v-if="seo.x_default"
                head-key="hreflang-x-default"
                rel="alternate"
                hreflang="x-default"
                :href="seo.x_default"
            />
        </template>
    </Head>
</template>

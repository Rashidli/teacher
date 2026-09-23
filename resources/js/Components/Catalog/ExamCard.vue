<script setup>
import { Link } from '@inertiajs/vue3';

/**
 * Kataloq kartı — kateqoriya səhifəsində və ümumi kataloqda eyni komponent.
 * Məlumat forması `CategoryController::examCard()`-dan gəlir.
 */
defineProps({
    exam: { type: Object, required: true },
    // Ümumi kataloqda imtahanın hansı bölmədən olduğu göstərilir; kateqoriya səhifəsində lazım deyil
    showCategory: { type: Boolean, default: false },
});
</script>

<template>
    <Link :href="exam.url" class="card">
        <span v-if="showCategory && exam.category" class="card-tag">{{ exam.category }}</span>
        <span class="card-name">{{ exam.title }}</span>
        <span class="card-short">
            <template v-if="exam.subjects.length">{{ exam.subjects.join(', ') }} · </template>
            {{ exam.questions_count }} {{ $t('category_page.questions') }}
            · {{ exam.duration_minutes }} {{ $t('category_page.minutes') }}
        </span>
        <span class="card-price">
            {{ exam.is_free ? $t('category_page.free') : `${exam.price} AZN` }}
        </span>
    </Link>
</template>

<style scoped>
.card {
    display: flex;
    flex-direction: column;
    gap: 4px;
    /* Grid övladı: uzun başlıq sütunu genişləndirib üfüqi sürüşmə yaratmasın */
    min-width: 0;
    height: 100%;
    padding: 16px;
    border: 1px solid rgba(22, 19, 14, 0.15);
    border-radius: 12px;
    text-decoration: none;
}

.card:hover {
    border-color: rgba(22, 19, 14, 0.4);
}

.card-tag {
    font-size: 0.8125rem;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    opacity: 0.6;
}

.card-name {
    font-weight: 600;
}

.card-short {
    /* Mobildə ikinci dərəcəli mətn 15px: oxunaqlıdır, iyerarxiya da qalır */
    font-size: 0.9375rem;
    opacity: 0.75;
}

.card-price {
    margin-top: auto;
    padding-top: 6px;
    font-weight: 600;
}

@media (min-width: 640px) {
    .card-short { font-size: 0.9rem; }
}
</style>

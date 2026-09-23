<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PanelHead from '@/Components/Ui/PanelHead.vue';
import PanelCard from '@/Components/Ui/PanelCard.vue';
import PanelButton from '@/Components/Ui/PanelButton.vue';
import PanelRow from '@/Components/Ui/PanelRow.vue';
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useLocale } from '@/Composables/useLocale';

// Kabinet siyahısı: yeni imtahan axtarışı kataloqdadır (kateqoriya ağacı), burada filtr yoxdur
const props = defineProps({
    inProgress: { type: Array, default: () => [] },
    available: { type: Array, default: () => [] },
    completed: { type: Array, default: () => [] },
});

const { lroute } = useLocale();

const sourceLabels = {
    payment: 'Ödəniş',
    manual: 'Admin icazəsi',
    free: 'Pulsuz',
};

const isEmpty = computed(
    () => !props.inProgress.length && !props.available.length && !props.completed.length,
);
</script>

<template>
    <Head title="Mənim imtahanlarım" />

    <AuthenticatedLayout>
        <template #header>
            <PanelHead title="Mənim imtahanlarım" lead="Davam edənlər, girişi açıq olanlar və tamamlananlar.">
                <template #actions>
                    <PanelButton :href="lroute('exams.catalog')">İmtahan seç</PanelButton>
                </template>
            </PanelHead>
        </template>

        <div class="wrap page">
            <div class="stack">
                <!-- Davam edən cəhdlər: taymer işlədiyi üçün birinci yerdədir -->
                <PanelCard v-if="inProgress.length" title="Davam edən imtahanlar" accent="var(--sign-yellow)" flush>
                    <ul class="rows">
                        <PanelRow
                            v-for="item in inProgress"
                            :key="item.attempt_id"
                            :title="item.title"
                            :trail="item.trail"
                        >
                            <template #meta>
                                <span class="urgent">{{ item.remaining_minutes }} dəqiqə qalıb</span>
                            </template>
                            <template #actions>
                                <PanelButton :href="item.url" small>Davam et</PanelButton>
                            </template>
                        </PanelRow>
                    </ul>
                </PanelCard>

                <!-- Girişi olan imtahanlar -->
                <PanelCard v-if="available.length" title="Girişi olan imtahanlar" accent="var(--pen)" flush>
                    <ul class="rows">
                        <PanelRow
                            v-for="item in available"
                            :key="item.url"
                            :title="item.title"
                            :trail="item.trail"
                        >
                            <template #meta>
                                {{ sourceLabels[item.source] ?? item.source }}
                                <template v-if="item.expires_at"> · {{ item.expires_at }} tarixinədək</template>
                            </template>
                            <template #actions>
                                <PanelButton :href="item.url" variant="ghost" small>Aç</PanelButton>
                            </template>
                        </PanelRow>
                    </ul>
                </PanelCard>

                <!-- Tamamlanmış cəhdlər -->
                <PanelCard v-if="completed.length" title="Tamamlanmış imtahanlar" accent="var(--correct)" flush>
                    <ul class="rows">
                        <PanelRow
                            v-for="item in completed"
                            :key="item.attempt_id"
                            :title="item.title"
                            :trail="item.trail"
                            :href="item.url"
                        >
                            <template #meta>
                                <template v-if="item.finished_at">{{ item.finished_at }}</template>
                            </template>
                            <template #actions>
                                <span class="score">{{ item.relative_score ?? 0 }}</span>
                                <span class="score-max">/ 100</span>
                            </template>
                        </PanelRow>
                    </ul>
                </PanelCard>

                <!-- Boş vəziyyət -->
                <PanelCard v-if="isEmpty">
                    <div class="empty">
                        <p class="empty-title">Hələ imtahanın yoxdur.</p>
                        <p class="empty-lead">Kataloqdan hazırlaşdığın bölməni seç və imtahanı aç.</p>
                        <PanelButton :href="lroute('exams.catalog')">Kataloqa keç</PanelButton>
                    </div>
                </PanelCard>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
.page {
    padding-block: 28px 64px;
}

.stack {
    display: grid;
    gap: 20px;
}

.rows {
    list-style: none;
    margin: 0;
    padding: 0;
}

.urgent {
    font-weight: 600;
    color: #8A5A05;
}

.score {
    font-family: var(--font-display);
    font-size: 1.25rem;
    font-weight: 700;
    color: var(--graphite);
}

.score-max {
    font-size: 0.875rem;
    color: var(--muted);
}

.empty {
    padding: 22px 0;
    text-align: center;
}

.empty-title {
    margin: 0;
    font-weight: 600;
    color: var(--graphite);
}

.empty-lead {
    margin: 6px 0 18px;
    font-size: 0.9375rem;
    color: var(--muted);
}
</style>

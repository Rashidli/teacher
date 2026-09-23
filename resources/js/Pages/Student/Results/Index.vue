<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PanelHead from '@/Components/Ui/PanelHead.vue';
import PanelCard from '@/Components/Ui/PanelCard.vue';
import PanelButton from '@/Components/Ui/PanelButton.vue';
import PanelRow from '@/Components/Ui/PanelRow.vue';
import { Head, Link } from '@inertiajs/vue3';
import { useLocale } from '@/Composables/useLocale';

/**
 * Nəticə siyahısı.
 *
 * Cədvəl əvəzinə sətir siyahısıdır: mobildə üfüqi sürüşmə yaratmır və "Qrup" sütunu
 * (qrupsuz imtahanlarda boş qalırdı) bölmə adı ilə əvəzləndi.
 */
defineProps({
    attempts: { type: Object, required: true },
});

const { lroute } = useLocale();

// Nisbi bal əsas ölçüdür; yoxlanmamış cəhddə rəng neytral qalır
const scoreClass = (attempt) => {
    if (attempt.awaiting_review) return 'score--pending';
    if (attempt.relative_score >= 80) return 'score--high';
    if (attempt.relative_score >= 60) return 'score--mid';

    return 'score--low';
};
</script>

<template>
    <Head title="Nəticələrim" />

    <AuthenticatedLayout>
        <template #header>
            <PanelHead title="Nəticələrim" lead="Bütün tamamlanmış cəhdlər, ən yenisi əvvəldə.">
                <template #actions>
                    <PanelButton :href="route('student.statistics')" variant="ghost">Statistika</PanelButton>
                </template>
            </PanelHead>
        </template>

        <div class="wrap page">
            <PanelCard flush>
                <ul v-if="attempts.data?.length" class="rows">
                    <PanelRow
                        v-for="attempt in attempts.data"
                        :key="attempt.id"
                        :title="attempt.title"
                        :trail="attempt.trail"
                        :href="attempt.url"
                    >
                        <template #meta>
                            <template v-if="attempt.subject">{{ attempt.subject }} · </template>
                            {{ attempt.finished_at }}
                            · {{ attempt.correct_answers }}/{{ attempt.questions }} düz
                        </template>
                        <template #actions>
                            <span class="score-block">
                                <span :class="['score', scoreClass(attempt)]">{{ attempt.relative_score }}</span>
                                <span class="score-max">/ 100</span>
                                <span v-if="attempt.awaiting_review" class="tag">ilkin</span>
                            </span>
                        </template>
                    </PanelRow>
                </ul>

                <div v-else class="empty">
                    <p class="empty-title">Hələ heç bir imtahan verməmisən.</p>
                    <p class="empty-lead">Kataloqdan bölməni seç və ilk imtahanını ver.</p>
                    <PanelButton :href="lroute('exams.catalog')">Kataloqa keç</PanelButton>
                </div>
            </PanelCard>

            <!-- Səhifələmə -->
            <nav v-if="attempts.data?.length && attempts.last_page > 1" class="pager" aria-label="Səhifələr">
                <span class="pager-state">{{ attempts.from }}–{{ attempts.to }} / {{ attempts.total }}</span>
                <span class="pager-links">
                    <Link
                        v-for="link in attempts.links"
                        :key="link.label"
                        :href="link.url || ''"
                        :class="['pager-link', link.active ? 'pager-link--on' : '', !link.url ? 'pager-link--off' : '']"
                        :aria-current="link.active ? 'page' : undefined"
                        preserve-scroll
                        v-html="link.label"
                    />
                </span>
            </nav>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
.page {
    padding-block: 28px 64px;
}

.rows {
    list-style: none;
    margin: 0;
    padding: 0;
}

.score-block {
    display: block;
    text-align: right;
}

.score {
    font-family: var(--font-display);
    font-size: 1.25rem;
    font-weight: 700;
}

.score--high { color: var(--correct); }
.score--mid { color: #8A5A05; }
.score--low { color: var(--ink-red); }
.score--pending { color: var(--muted); }

.score-max {
    font-size: 0.875rem;
    color: var(--muted);
}

.tag {
    display: block;
    margin-top: 2px;
    font-size: 0.75rem;
    font-weight: 600;
    color: #8A5A05;
}

.empty {
    padding: 28px 18px;
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

/* ------------------------------------------------------------ səhifələmə */

.pager {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    margin-top: 20px;
}

.pager-state {
    font-size: 0.875rem;
    color: var(--muted);
}

.pager-links {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
}

.pager-link {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    /* Toxunma sahəsi 44px */
    min-width: 44px;
    min-height: 44px;
    padding-inline: 10px;
    border: 1px solid rgba(22, 19, 14, 0.25);
    border-radius: 999px;
    font-size: 0.9rem;
    color: var(--graphite);
    text-decoration: none;
}

.pager-link--on {
    background: var(--graphite);
    border-color: var(--graphite);
    color: var(--paper);
    font-weight: 600;
}

.pager-link--off {
    opacity: 0.35;
    pointer-events: none;
}
</style>

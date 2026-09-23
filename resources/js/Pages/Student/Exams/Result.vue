<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PanelHead from '@/Components/Ui/PanelHead.vue';
import PanelCard from '@/Components/Ui/PanelCard.vue';
import PanelButton from '@/Components/Ui/PanelButton.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import MathText from '@/Components/MathText.vue';
import QuestionImage from '@/Components/QuestionImage.vue';
import LineChart from '@/Components/Charts/LineChart.vue';
import ScoreBar from '@/Components/Charts/ScoreBar.vue';
import { computed } from 'vue';

/**
 * Nəticə səhifəsi.
 *
 * İki hissədən ibarətdir:
 *  1. **İmtahan vərəqi** — ekranda da, çapda da SƏNƏD kimi görünən xülasə: başlıq,
 *     cavab kartı cədvəli və yekun. `@media print` ilə menyu və düymələr gizlənir.
 *  2. **Sual-cavab analizi** — hər sualın açılışı (əvvəlki bölmə, olduğu kimi qalır).
 */
const props = defineProps({
    attempt: Object,
    sections: { type: Array, default: () => [] },
    exam: Object,
    // İctimai imtahan səhifəsi: "Yenidən imtahan ver" ora aparır
    examUrl: { type: String, default: null },
    answers: Array,
    // Eyni imtahanın əvvəlki cəhdləri və bu cəhdin mövzu bölgüsü
    comparison: { type: Object, default: () => ({ history: [], previous: null, change: null }) },
    topics: { type: Array, default: () => [] },
});

const historyPoints = computed(() => (props.comparison.history ?? []).map((item) => ({
    label: item.date ?? '',
    value: item.relative_score ?? 0,
})));

/*
 * Əsas göstərici NİSBİ BALDIR (NB). Açıq suallar yoxlanmayıbsa bal İLKİNDİR:
 * rəng neytral qalır, yekun bal yalnız yoxlamadan sonra vurğulanır.
 */
const relative = computed(() => Number(props.attempt.relative_score ?? 0));

const getScoreColor = computed(() => {
    if (props.attempt.awaiting_review) return 'score--pending';
    if (relative.value >= 80) return 'score--high';
    if (relative.value >= 60) return 'score--mid';

    return 'score--low';
});

// "Qrup: " sətri qrupsuz imtahanlarda boş qalırdı (MİQ, sürücülük — group_id NULL)
const contextLabel = computed(() => props.attempt.group?.name || props.exam?.category || null);

const accent = computed(() => props.attempt.trail?.color || 'var(--graphite)');

const trailText = computed(
    () => [props.attempt.trail?.root, props.attempt.trail?.leaf].filter(Boolean).join(' › '),
);

/*
 * Vərəq onsuz da düz/səhv/boş saylarını və balı göstərir, ona görə aşağıdakı bloklar
 * yalnız YENİ məlumat verəndə çıxır:
 *  - bölmə cədvəli: imtahan çoxfənlidirsə (tək bölmədə rəqəmlər eynidir);
 *  - irəliləyiş: ən azı iki cəhd varsa;
 *  - qrafik: ən azı üç cəhd varsa (iki nöqtəli qrafik sətirdən artıq şey demir).
 */
const showSections = computed(() => props.sections.length > 1);

const showChart = computed(() => historyPoints.value.length > 2);

const showProgress = computed(() => Boolean(props.comparison.previous) || showChart.value);

const finishedAt = computed(() => (props.attempt.finished_at
    ? new Date(props.attempt.finished_at).toLocaleString('az-AZ')
    : null));

/* ----------------------------------------------------------- cavab kartı */

/**
 * Sualın vəziyyəti — cavab kartının rəngi və işarəsi buradan gəlir.
 * Rəng TƏK göstərici deyil: hər xanada işarə (✓ ✗ — ?) də var.
 */
const statusOf = (answer) => {
    if (answer.awaiting_review) return 'pending';

    const answered = answer.type === 'multiple_choice'
        ? Boolean(answer.selected_option_id)
        : Boolean(answer.open_answer);

    if (!answered) return 'empty';
    if (answer.is_correct) return 'correct';
    if (answer.grade_ratio > 0) return 'partial';

    return 'wrong';
};

const STATUS_MARK = { correct: '✓', wrong: '✗', empty: '—', pending: '?', partial: '±' };

const letterOf = (answer, optionId) => answer.options
    ?.find((option) => option.id === optionId)?.option_letter ?? null;

const short = (value) => {
    const text = String(value ?? '');

    return text.length > 8 ? `${text.slice(0, 7)}…` : text;
};

/**
 * Şagirdin cavabı — qapalıda hərf, kodlaşdırılan tapşırıqda kod ("A, C" / "1 → 2"),
 * yazılıda isə yalnız "yazılıb" işarəsi.
 */
const givenAnswer = (answer) => {
    if (answer.type === 'multiple_choice') {
        return letterOf(answer, answer.selected_option_id) ?? '—';
    }

    if (answer.given_code) return short(answer.given_code);

    if (!answer.open_answer) return '—';

    return answer.open_answer.length > 8 ? '✎' : answer.open_answer;
};

/** Düzgün cavab — qapalıda hərf, kodlaşdırılanda kod və ya etalon, yazılıda "—" */
const rightAnswer = (answer) => {
    if (answer.type === 'multiple_choice') {
        return letterOf(answer, answer.correct_option_id) ?? '—';
    }

    if (answer.correct_code) return short(answer.correct_code);

    if (answer.accepted_answers?.length) return short(answer.accepted_answers[0]);

    return '—';
};

const tally = computed(() => {
    const counts = { correct: 0, wrong: 0, empty: 0, pending: 0, partial: 0 };

    (props.answers ?? []).forEach((answer) => {
        counts[statusOf(answer)] += 1;
    });

    return counts;
});

const totalWeight = computed(
    () => (props.answers ?? []).reduce((sum, answer) => sum + Number(answer.weight ?? 1), 0),
);

const printSheet = () => window.print();

/* -------------------------------------------------------------- etirazlar */

const SCALE = [[0, '0'], [1 / 3, '1/3'], [0.5, '1/2'], [2 / 3, '2/3'], [1, '1']];

const gradeLabel = (ratio) => {
    const value = Number(ratio);
    const found = SCALE.find(([step]) => Math.abs(step - value) < 0.01);

    return found ? found[1] : value.toFixed(2);
};

const reviewForm = useForm({});

const requestReview = (answer) => {
    reviewForm.post(
        route('student.exams.request-review', { attempt: props.attempt.id, answer: answer.answer_id }),
        { preserveScroll: true },
    );
};

const getAnswerStatus = (answer) => statusOf(answer);
</script>

<template>
    <Head :title="`Nəticə — ${exam?.title ?? ''}`" />

    <AuthenticatedLayout>
        <template #header>
            <PanelHead :title="exam?.title ?? 'Nəticə'" :lead="trailText || null">
                <template #actions>
                    <PanelButton variant="ghost" @click="printSheet">Çap et / PDF</PanelButton>
                    <PanelButton :href="examUrl">Yenidən ver</PanelButton>
                </template>
            </PanelHead>
        </template>

        <div class="wrap page" :style="{ '--accent': accent }">
            <!--
                İMTAHAN VƏRƏQİ — ekranda da, çapda da sənəd kimi. Aşağıdakı sual-cavab
                analizi olduğu kimi qalır, vərəq onun üstündə xülasədir.
            -->
            <article class="sheet">
                <header class="sheet-head">
                    <div class="sheet-brand">
                        <span class="brand-mark" aria-hidden="true"></span>
                        <span class="brand-name">{{ $t('site.brand') }}</span>
                    </div>
                    <p class="sheet-doc">İmtahan vərəqi</p>
                </header>

                <dl class="sheet-meta">
                    <div>
                        <dt>Şagird</dt>
                        <dd>{{ attempt.student || '—' }}</dd>
                    </div>
                    <div>
                        <dt>İmtahan</dt>
                        <dd>{{ exam?.title }}</dd>
                    </div>
                    <div>
                        <dt>Bölmə</dt>
                        <dd>{{ trailText || contextLabel || '—' }}</dd>
                    </div>
                    <div>
                        <dt>Tarix</dt>
                        <dd>{{ finishedAt }}<template v-if="attempt.minutes_spent"> · {{ attempt.minutes_spent }} dəq</template></dd>
                    </div>
                </dl>

                <div class="sheet-body">
                    <!-- Cavab kartı: mobildə ÖZ konteynerində sürüşür, səhifəni yana çəkmir -->
                    <div class="card-scroll">
                        <table class="answer-card">
                            <caption class="visually-hidden">Cavab kartı: sual nömrəsi, sənin cavabın, düzgün cavab və sualın dəyəri</caption>
                            <tbody>
                                <tr>
                                    <th scope="row">№</th>
                                    <td v-for="(answer, index) in answers" :key="`n-${answer.question_id}`" class="cell-num">
                                        {{ index + 1 }}
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">Cavabın</th>
                                    <td
                                        v-for="answer in answers"
                                        :key="`g-${answer.question_id}`"
                                        :class="['cell', `cell--${statusOf(answer)}`]"
                                    >
                                        <span class="cell-mark" aria-hidden="true">{{ STATUS_MARK[statusOf(answer)] }}</span>
                                        <span class="cell-value">{{ givenAnswer(answer) }}</span>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">Düzgün</th>
                                    <td v-for="answer in answers" :key="`r-${answer.question_id}`" class="cell-right">
                                        {{ rightAnswer(answer) }}
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">Dəyər</th>
                                    <td v-for="answer in answers" :key="`w-${answer.question_id}`" class="cell-weight">
                                        {{ answer.weight }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Yekun -->
                    <aside class="summary">
                        <p class="summary-score">
                            <span :class="['score', getScoreColor]">{{ attempt.relative_score }}</span>
                            <span class="score-max">/ 100</span>
                            <span v-if="attempt.awaiting_review" class="tag tag--pending">ilkin bal</span>
                        </p>
                        <p class="summary-sub">
                            {{ attempt.score }} / {{ attempt.max_subject_score }} bal
                            · {{ totalWeight }} xam bal
                        </p>

                        <ul class="legend">
                            <li><span class="dot dot--correct" aria-hidden="true">✓</span> düz <b>{{ tally.correct }}</b></li>
                            <li v-if="tally.partial"><span class="dot dot--partial" aria-hidden="true">±</span> qismən <b>{{ tally.partial }}</b></li>
                            <li><span class="dot dot--wrong" aria-hidden="true">✗</span> səhv <b>{{ tally.wrong }}</b></li>
                            <li><span class="dot dot--empty" aria-hidden="true">—</span> cavabsız <b>{{ tally.empty }}</b></li>
                            <li v-if="tally.pending"><span class="dot dot--pending" aria-hidden="true">?</span> yoxlanılır <b>{{ tally.pending }}</b></li>
                        </ul>

                        <!--
                            Şagird ilkin balı yekun sanırdı. Ona görə burada konkret rəqəm var:
                            neçə sual yoxlanılır və ən çoxu nə qədər bal gələ bilər.
                        -->
                        <p v-if="attempt.awaiting_review" class="summary-note">
                            Bu <b>ilkin baldır</b>: yoxlanılan
                            {{ attempt.pending_review_count }} sual üçün əlavə
                            <b>{{ attempt.pending_max_relative }} bala</b> qədər gələ bilər.
                            Cavab kartında həmin suallar <b>?</b> ilə işarələnib.
                        </p>
                    </aside>
                </div>
            </article>

            <!--
                Bölmə cədvəli YALNIZ çoxfənli imtahanda göstərilir: tək bölməli imtahanda
                rəqəmlər vərəqdəki yekunla eynidir, təkrar olardı.
            -->
            <PanelCard v-if="showSections" title="Fənn üzrə bölgü" class="block" flush>
                <div class="card-scroll">
                    <table class="table">
                        <thead>
                            <tr>
                                <th class="cell-left">Fənn</th>
                                <th>Düz</th>
                                <th>Səhv</th>
                                <th>Boş</th>
                                <th>Nisbi bal</th>
                                <th>Fənn balı</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="section in sections" :key="section.title">
                                <td class="cell-left cell-name">{{ section.title }}</td>
                                <td class="up">{{ section.correct_answers }}</td>
                                <td class="down">{{ section.wrong_answers }}</td>
                                <td class="muted">{{ section.unanswered }}</td>
                                <td>{{ section.relative_score }}</td>
                                <td class="cell-name">
                                    {{ section.subject_score }}<span class="muted"> / {{ section.max_score }}</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </PanelCard>

            <!-- Mövzu bölgüsü -->
            <PanelCard v-if="topics.length > 1" title="Mövzu üzrə bölgü" class="block">
                <p class="hint">Ən zəif mövzu yuxarıdadır.</p>
                <ul class="topics">
                    <li v-for="topic in topics" :key="topic.topic">
                        <div class="topic-line">
                            <span class="topic-name">{{ topic.topic }}</span>
                            <span class="muted">{{ topic.correct }}/{{ topic.answered }}</span>
                        </div>
                        <ScoreBar :value="topic.accuracy" :weak="topic.accuracy < 60" class="bar" />
                    </li>
                </ul>
            </PanelCard>

            <!-- Sual-cavab analizi: vərəqdəki xülasənin açılışı -->
            <PanelCard title="Sual-cavab analizi" class="block" flush>
                    <div class="analysis">
                        <div
                            v-for="(answer, index) in answers"
                            :key="answer.question_id"
                            class="p-6"
                        >
                            <div class="flex items-start gap-4">
                                <span
                                    :class="[
                                        'flex-shrink-0 w-8 h-8 rounded-full flex items-center justify-center text-sm font-medium',
                                        getAnswerStatus(answer) === 'correct'
                                            ? 'bg-green-100 text-green-800'
                                            : getAnswerStatus(answer) === 'wrong'
                                                ? 'bg-red-100 text-red-800'
                                                : 'bg-gray-100 text-gray-600'
                                    ]"
                                >
                                    {{ index + 1 }}
                                </span>
                                <div class="flex-1">
                                    <p class="text-gray-900 font-medium"><MathText :text="answer.question_text" /></p>
                                    <QuestionImage :url="answer.question_image_url" :alt="answer.question_image_alt" />

                                    <div v-if="answer.options?.length" class="mt-4 space-y-2">
                                        <div
                                            v-for="option in answer.options"
                                            :key="option.id"
                                            :class="[
                                                'p-3 rounded-lg text-sm',
                                                option.is_correct
                                                    ? 'bg-green-50 border border-green-200'
                                                    : option.id === answer.selected_option_id && !option.is_correct
                                                        ? 'bg-red-50 border border-red-200'
                                                        : 'bg-gray-50'
                                            ]"
                                        >
                                            <div class="flex items-center justify-between">
                                                <span>
                                                    <MathText :text="option.option_text" />
                                                    <QuestionImage
                                                        v-if="option.option_image_url"
                                                        :url="option.option_image_url"
                                                        :alt="option.option_text || 'Variant'"
                                                        size="option"
                                                        class="mt-2"
                                                    />
                                                </span>
                                                <div class="flex items-center gap-2">
                                                    <span
                                                        v-if="option.id === answer.selected_option_id"
                                                        class="text-xs px-2 py-0.5 rounded bg-indigo-100 text-indigo-800"
                                                    >
                                                        Seçiminiz
                                                    </span>
                                                    <span
                                                        v-if="option.is_correct"
                                                        class="text-xs px-2 py-0.5 rounded bg-green-100 text-green-800"
                                                    >
                                                        Düzgün cavab
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Açıq suallar: cavab, düzgün cavab/meyar, qiymət və əsaslandırma -->
                                    <div v-if="answer.type !== 'multiple_choice'" class="mt-4 space-y-3 text-sm">
                                        <div>
                                            <p class="font-medium text-gray-700">Sənin cavabın</p>
                                            <p v-if="answer.open_answer" class="mt-1 whitespace-pre-line rounded-lg bg-gray-50 p-3 text-gray-900">{{ answer.open_answer }}</p>
                                            <p v-else class="mt-1 italic text-gray-500">Bu suala cavab verilməyib</p>
                                        </div>

                                        <div v-if="answer.accepted_answers?.length">
                                            <p class="font-medium text-gray-700">Qəbul olunan cavablar</p>
                                            <p class="mt-1 text-green-700">{{ answer.accepted_answers.join(', ') }}</p>
                                        </div>

                                        <div v-if="answer.grading_rubric">
                                            <p class="font-medium text-gray-700">Düzgün cavab və meyar</p>
                                            <p class="mt-1 whitespace-pre-line text-gray-700">{{ answer.grading_rubric }}</p>
                                        </div>

                                        <div v-if="answer.awaiting_review" class="rounded-lg bg-amber-50 p-3 text-amber-800">
                                            Bu cavab yoxlanılır — qiymət sonra əlavə olunacaq.
                                        </div>

                                        <div v-else-if="answer.grade_ratio !== null && answer.grade_ratio !== undefined" class="rounded-lg bg-gray-50 p-3">
                                            <p class="font-medium text-gray-800">
                                                Qiymət: {{ gradeLabel(answer.grade_ratio) }}
                                                <span class="text-gray-500">({{ answer.score_earned }} bal)</span>
                                            </p>
                                            <p v-if="answer.grade_comment" class="mt-1 text-gray-700">{{ answer.grade_comment }}</p>

                                            <template v-if="answer.grade_source === 'ai'">
                                                <p class="mt-2 text-xs text-gray-500">
                                                    İlkin qiymət avtomatik verilib. Razı deyilsənsə, müəllim
                                                    yenidən baxa bilər.
                                                </p>
                                                <p v-if="answer.review_requested" class="mt-1 text-xs font-medium text-indigo-700">
                                                    Yenidən baxış istənilib.
                                                </p>
                                                <button
                                                    v-else
                                                    type="button"
                                                    class="mt-2 rounded-lg border border-indigo-300 px-3 py-1.5 text-xs font-medium text-indigo-700 hover:bg-indigo-50 disabled:opacity-50"
                                                    :disabled="reviewForm.processing"
                                                    @click="requestReview(answer)"
                                                >Yenidən baxılsın</button>
                                            </template>
                                        </div>
                                    </div>

                                    <p
                                        v-else-if="!answer.selected_option_id"
                                        class="mt-2 text-sm text-gray-500 italic"
                                    >
                                        Bu suala cavab verilməyib
                                    </p>

                                    <p v-if="answer.explanation" class="mt-3 rounded-lg bg-blue-50 p-3 text-sm text-blue-900">
                                        <span class="font-medium">İzah:</span> {{ answer.explanation }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
            </PanelCard>

            <!--
                İrəliləyiş: bir cəhddə bölmə ümumiyyətlə yoxdur, iki cəhddə bir sətir,
                üç və daha çox cəhddə qrafik.
            -->
            <PanelCard v-if="showProgress" title="İrəliləyiş" class="block">
                <p v-if="comparison.previous" class="compare">
                    Əvvəlki cəhd ({{ comparison.previous.date }}):
                    <b>{{ comparison.previous.relative_score }}</b>,
                    dəyişmə:
                    <b :class="comparison.change >= 0 ? 'up' : 'down'">
                        {{ comparison.change > 0 ? '+' : '' }}{{ comparison.change }}
                    </b>
                </p>

                <div v-if="showChart" class="chart">
                    <LineChart :points="historyPoints" :max="100" label="Bu imtahandakı cəhdlərin nisbi balı" />
                </div>
            </PanelCard>

            <div class="actions">
                <PanelButton :href="route('student.exams.index')" variant="ghost">Mənim imtahanlarım</PanelButton>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
.page {
    padding-block: 28px 64px;
}

.block {
    margin-top: 20px;
}

/* ============================================================ VƏRƏQ */

.sheet {
    border: var(--card-border);
    border-top: 4px solid var(--accent);
    border-radius: var(--card-radius);
    background: var(--paper);
}

.sheet-head {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 8px 16px;
    padding: 16px 18px;
    border-bottom: 1px solid var(--ink-red-line);
}

.sheet-brand {
    display: inline-flex;
    align-items: center;
    gap: 10px;
}

/* İctimai başlıqdakı nişanın eynisi */
.brand-mark {
    width: 18px;
    height: 18px;
    flex: none;
    border-radius: 50%;
    border: 1.5px solid var(--ink-red);
    background: radial-gradient(circle at 40% 38%, #3a3f46 0 45%, var(--graphite) 70%);
    box-shadow: inset 0 0 0 2px var(--paper);
}

.brand-name {
    font-family: var(--font-display);
    font-weight: 600;
    color: var(--graphite);
}

.sheet-doc {
    margin: 0;
    font-size: 0.8125rem;
    font-weight: 600;
    letter-spacing: 0.06em;
    text-transform: uppercase;
    color: var(--muted);
}

.sheet-meta {
    display: grid;
    gap: 10px 24px;
    margin: 0;
    padding: 14px 18px;
    border-bottom: 1px dashed var(--ink-red-line);
    grid-template-columns: repeat(2, minmax(0, 1fr));
}

.sheet-meta div {
    min-width: 0;
}

.sheet-meta dt {
    font-size: 0.75rem;
    letter-spacing: 0.03em;
    text-transform: uppercase;
    color: var(--muted);
}

.sheet-meta dd {
    margin: 2px 0 0;
    font-weight: 600;
    color: var(--graphite);
    overflow-wrap: anywhere;
}

.sheet-body {
    display: grid;
    gap: 18px;
    padding: 16px 18px 18px;
}

/* ------------------------------------------------------- cavab kartı */

/* Cədvəl ÖZ konteynerində sürüşür — səhifə yana çəkilmir */
.card-scroll {
    overflow-x: auto;
    overscroll-behavior-x: contain;
}

.answer-card {
    border-collapse: collapse;
    font-size: 0.875rem;
}

.answer-card th[scope='row'] {
    position: sticky;
    left: 0;
    z-index: 1;
    padding: 6px 12px 6px 0;
    background: var(--paper);
    text-align: left;
    font-size: 0.75rem;
    font-weight: 600;
    letter-spacing: 0.03em;
    text-transform: uppercase;
    color: var(--muted);
    white-space: nowrap;
}

.answer-card td {
    min-width: 42px;
    padding: 5px 4px;
    border: 1px solid rgba(22, 19, 14, 0.12);
    text-align: center;
    vertical-align: middle;
}

.cell-num {
    font-weight: 700;
    color: var(--graphite);
    background: var(--paper-sunk);
}

/*
 * Vəziyyət: YUMŞAQ fon + sol kənarda rəngli zolaq + işarə.
 * Fon tam doldurulmur ki, mətn oxunaqlı qalsın; rəng tək göstərici deyil.
 */
.cell {
    border-left-width: 3px;
    border-left-style: solid;
    line-height: 1.15;
}

.cell-mark {
    display: block;
    font-size: 0.8125rem;
    font-weight: 700;
}

.cell-value {
    display: block;
    font-size: 0.75rem;
    color: var(--muted);
}

.cell--correct { background: #E6EFEA; border-left-color: var(--correct); color: var(--correct); }
.cell--partial { background: #F7F0E0; border-left-color: #8A5A05; color: #8A5A05; }
.cell--wrong { background: #F7E8E9; border-left-color: var(--ink-red); color: var(--ink-red); }
.cell--empty { background: var(--paper-sunk); border-left-color: rgba(22, 19, 14, 0.3); color: var(--muted); }
.cell--pending { background: #E9ECF6; border-left-color: var(--pen); color: var(--pen); }

.cell-right {
    font-weight: 600;
    color: var(--graphite);
}

.cell-weight {
    font-size: 0.75rem;
    color: var(--muted);
}

/* ------------------------------------------------------------- yekun */

.summary {
    padding: 14px 16px;
    border: 1px solid rgba(22, 19, 14, 0.12);
    border-radius: 10px;
    background: var(--paper-sunk);
}

.summary-score {
    display: flex;
    flex-wrap: wrap;
    align-items: baseline;
    gap: 6px;
    margin: 0;
}

.score {
    font-family: var(--font-display);
    font-size: 2rem;
    font-weight: 700;
    line-height: 1;
}

.score--high { color: var(--correct); }
.score--mid { color: #8A5A05; }
.score--low { color: var(--ink-red); }
.score--pending { color: var(--muted); }

.score-max {
    font-size: 0.9375rem;
    color: var(--muted);
}

.tag {
    padding: 2px 10px;
    border-radius: 999px;
    font-size: 0.75rem;
    font-weight: 700;
}

.tag--pending {
    background: #F7F0E0;
    color: #8A5A05;
}

.summary-sub {
    margin: 4px 0 12px;
    font-size: 0.875rem;
    color: var(--muted);
}

.legend {
    list-style: none;
    margin: 0;
    padding: 0;
    display: grid;
    gap: 6px;
    font-size: 0.875rem;
    color: var(--graphite);
}

.legend li {
    display: flex;
    align-items: center;
    gap: 8px;
}

.dot {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 20px;
    height: 20px;
    flex: none;
    border-radius: 5px;
    font-size: 0.75rem;
    font-weight: 700;
}

.dot--correct { background: #E6EFEA; color: var(--correct); }
.dot--partial { background: #F7F0E0; color: #8A5A05; }
.dot--wrong { background: #F7E8E9; color: var(--ink-red); }
.dot--empty { background: #E7E7E4; color: var(--muted); }
.dot--pending { background: #E9ECF6; color: var(--pen); }

.summary-note {
    margin: 12px 0 0;
    font-size: 0.8125rem;
    color: #8A5A05;
}

/* -------------------------------------------------- bölmələr və qrafik */

.compare {
    margin: 0;
    font-size: 0.9375rem;
    color: var(--graphite);
}

.up { color: var(--correct); }
.down { color: var(--ink-red); }

.chart {
    margin-top: 14px;
    /*
     * Qrafikin sağındakı son tarix kəsilməsin: konteynerin sağında boşluq saxlanılır.
     */
    padding-right: 12px;
}

/* --------------------------------------------------------- bölmə cədvəli */

.table {
    width: 100%;
    min-width: 520px;
    border-collapse: collapse;
    font-size: 0.875rem;
}

.table th {
    padding: 10px 12px;
    text-align: right;
    font-weight: 600;
    color: var(--muted);
    white-space: nowrap;
}

.table td {
    padding: 10px 12px;
    text-align: right;
    border-top: 1px dashed var(--ink-red-line);
}

.cell-left {
    text-align: left;
}

.cell-name {
    font-weight: 600;
    color: var(--graphite);
}

.actions {
    margin-top: 24px;
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
}

@media (min-width: 640px) {
    .sheet-meta { grid-template-columns: repeat(4, minmax(0, 1fr)); }
}

@media (min-width: 1024px) {
    /* Cavab kartı solda, yekun sağda */
    .sheet-body {
        grid-template-columns: minmax(0, 1fr) 280px;
        align-items: start;
    }
}


/* -------------------------------------------- mövzu bölgüsü və analiz */

.hint {
    margin: 0 0 12px;
    font-size: 0.8125rem;
    color: var(--muted);
}

.topics {
    list-style: none;
    margin: 0;
    padding: 0;
    display: grid;
    gap: 14px;
}

.topic-line {
    display: flex;
    justify-content: space-between;
    gap: 10px;
    font-size: 0.9375rem;
}

.topic-name {
    font-weight: 600;
    color: var(--graphite);
}

.bar {
    margin-top: 6px;
}

.muted {
    color: var(--muted);
}

/*
 * Analizdəki sual blokları: sərhədlər və fonlar palitradan gəlir.
 * Daxili quruluş (Tailwind şəbəkəsi) olduğu kimi qalır — yalnız rənglər dəyişir.
 */
.analysis > div > div {
    border-top: 1px dashed var(--ink-red-line);
}

.analysis > div > div:first-child {
    border-top: 0;
}

.analysis :deep(.bg-gray-50) { background: var(--paper-sunk) !important; }
.analysis :deep(.bg-green-50) { background: #E6EFEA !important; }
.analysis :deep(.bg-red-50) { background: #F7E8E9 !important; }
.analysis :deep(.bg-blue-50) { background: #E9ECF6 !important; }
.analysis :deep(.bg-amber-50) { background: #F7F0E0 !important; }
.analysis :deep(.text-gray-900) { color: var(--graphite) !important; }
.analysis :deep(.text-gray-700),
.analysis :deep(.text-gray-800) { color: var(--graphite) !important; }
.analysis :deep(.text-gray-500) { color: var(--muted) !important; }
.analysis :deep(.text-green-800) { color: var(--correct) !important; }
.analysis :deep(.text-blue-900) { color: var(--pen-deep) !important; }
.analysis :deep(.text-amber-800) { color: #8A5A05 !important; }
.analysis :deep(.text-indigo-700),
.analysis :deep(.text-indigo-800) { color: var(--pen) !important; }
.analysis :deep(.border-green-200) { border-color: rgba(31, 122, 77, 0.35) !important; }
.analysis :deep(.border-red-200) { border-color: var(--ink-red-line) !important; }

/* Etiraz düyməsi: toxunma sahəsi 44px */
.analysis :deep(button) {
    min-height: 44px;
}

/* ============================================================== ÇAP */

@media print {
    .page {
        padding: 0;
        max-width: none;
    }

    /* Düymələr və interaktiv elementlər kağıza düşmür */
    .actions,
    .chart {
        display: none !important;
    }

    .sheet {
        border: 1px solid #999;
        border-top-width: 3px;
        border-radius: 0;
        background: #fff;
    }

    .summary,
    .cell-num,
    .compare {
        background: #fff !important;
    }

    /* Rəngli fonlar çapda saxlanılsın (brauzer defolt olaraq onları atır) */
    .cell,
    .dot {
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }

    /* Cavab kartı çapda tam görünsün */
    .card-scroll {
        overflow: visible;
    }

    .answer-card {
        font-size: 0.75rem;
    }

    /* Vərəq və hər sual ortadan kəsilməsin */
    .sheet,
    .block {
        break-inside: avoid;
        page-break-inside: avoid;
    }
}
</style>

<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import MathText from '@/Components/MathText.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';

const props = defineProps({
    exam: Object,
    sections: { type: Array, default: () => [] },
    subjects: { type: Array, default: () => [] },
    questionsTotal: { type: Number, default: 0 },
});

// Bölmə əlavə etmə forması
const sectionForm = useForm({ subject_id: '', title: '', question_count: '', max_score: '' });

const addSection = () => sectionForm.post(route('admin.exams.sections.store', props.exam.id), {
    preserveScroll: true,
    onSuccess: () => sectionForm.reset(),
});

const removeSection = (section) => {
    if (confirm(`"${section.title}" bölməsi silinsin?`)) {
        router.delete(route('admin.exams.sections.destroy', [props.exam.id, section.id]), {
            preserveScroll: true,
        });
    }
};

const QUESTION_TYPE_LABELS = {
    multiple_choice: 'Test',
    open_coded: 'Qısa cavab',
    open_written: 'Açıq (əl ilə yoxlanır)',
};

const moveQuestion = (question, direction) => {
    router.post(route('admin.exams.questions.move', [props.exam.id, question.id, direction]), {}, {
        preserveScroll: true,
    });
};

// Sual bankdan silinmir, yalnız bu imtahandan ayrılır
// Generasiyadan sonra bəyənilməyən sualı eyni hovuzdan başqası ilə əvəz etmək
const replaceQuestion = (question) => {
    router.post(route('admin.exams.questions.replace', [props.exam.id, question.id]), {}, {
        preserveScroll: true,
    });
};

const detachQuestion = (question) => {
    if (confirm('Sual bu imtahandan ayrılsın? Bankda qalacaq və digər imtahanlara təsir etməyəcək.')) {
        router.delete(route('admin.exams.questions.destroy', [props.exam.id, question.id]));
    }
};

const togglePublish = () => {
    useForm({}).post(route('admin.exams.toggle-publish', props.exam.id));
};

const toggleActive = () => {
    useForm({}).post(route('admin.exams.toggle-active', props.exam.id));
};
</script>

<template>
    <Head :title="`İmtahan: ${exam.title}`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">
                    İmtahan Detalları
                </h2>
                <Link
                    :href="route('admin.exams.index')"
                    class="text-sm text-indigo-600 hover:text-indigo-800"
                >
                    Geri
                </Link>
            </div>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-sm rounded-lg">
                    <div class="p-6">
                        <!-- Exam Info -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                            <div>
                                <h3 class="text-lg font-semibold text-gray-900 mb-4">İmtahan Məlumatları</h3>
                                <dl class="space-y-3">
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500">Başlıq</dt>
                                        <dd class="text-sm text-gray-900">{{ exam.title }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500">Təsvir</dt>
                                        <dd class="text-sm text-gray-900">{{ exam.description || '-' }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500">Fənn</dt>
                                        <dd>
                                            <span class="px-2 py-1 text-xs bg-indigo-100 text-indigo-800 rounded">
                                                {{ exam.subject?.name }}
                                            </span>
                                        </dd>
                                    </div>
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500">Müddət</dt>
                                        <dd class="text-sm text-gray-900">{{ exam.duration_minutes }} dəqiqə</dd>
                                    </div>
                                </dl>
                            </div>

                            <div>
                                <h3 class="text-lg font-semibold text-gray-900 mb-4">Sahiblik & Status</h3>
                                <dl class="space-y-3">
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500">Yaradan</dt>
                                        <dd class="text-sm text-gray-900">{{ exam.creator?.full_name ?? '—' }}</dd>
                                    </div>
                                    <div v-if="exam.teacher">
                                        <dt class="text-sm font-medium text-gray-500">Müəllim</dt>
                                        <dd class="text-sm text-gray-900">{{ exam.teacher.full_name }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500">Sual Sayı</dt>
                                        <dd class="text-sm text-gray-900">{{ exam.questions?.length || 0 }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500">İştirakçı Sayı</dt>
                                        <dd class="text-sm text-gray-900">{{ exam.attempts_count || 0 }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500">Yaradılma Tarixi</dt>
                                        <dd class="text-sm text-gray-900">
                                            {{ new Date(exam.created_at).toLocaleDateString('az-AZ') }}
                                        </dd>
                                    </div>
                                </dl>
                            </div>
                        </div>

                        <!-- Actions -->
                        <div class="border-t border-gray-200 pt-6 mb-8">
                            <h3 class="text-lg font-semibold text-gray-900 mb-4">Əməliyyatlar</h3>
                            <div class="flex gap-4">
                                <button
                                    @click="togglePublish"
                                    :class="[
                                        'px-4 py-2 rounded-lg',
                                        exam.is_published
                                            ? 'bg-yellow-600 text-white hover:bg-yellow-700'
                                            : 'bg-green-600 text-white hover:bg-green-700'
                                    ]"
                                >
                                    {{ exam.is_published ? 'Dərci Ləğv Et' : 'Dərc Et' }}
                                </button>
                                <button
                                    @click="toggleActive"
                                    :class="[
                                        'px-4 py-2 rounded-lg',
                                        exam.is_active
                                            ? 'bg-red-600 text-white hover:bg-red-700'
                                            : 'bg-green-600 text-white hover:bg-green-700'
                                    ]"
                                >
                                    {{ exam.is_active ? 'Deaktiv Et' : 'Aktiv Et' }}
                                </button>
                            </div>
                        </div>

                        <!-- Bölmələr və suallar -->
                        <div class="border-t border-gray-200 pt-6">
                            <div class="flex items-center justify-between mb-4">
                                <h3 class="text-lg font-semibold text-gray-900">
                                    Bölmələr və suallar
                                    <span class="ml-2 text-sm font-normal text-gray-500">
                                        ({{ questionsTotal }} sual)
                                    </span>
                                </h3>
                                <Link
                                    :href="route('admin.exams.questions.import', exam.id)"
                                    class="px-4 py-2 bg-gray-100 text-gray-800 text-sm rounded-lg hover:bg-gray-200"
                                >
                                    Toplu import
                                </Link>
                            </div>

                            <!-- Yeni bölmə -->
                            <form @submit.prevent="addSection"
                                class="mb-6 rounded-lg border border-gray-200 p-4 grid gap-3 sm:grid-cols-5 items-end">
                                <div class="sm:col-span-2">
                                    <label class="block text-xs font-medium text-gray-600">Fənn əlavə et</label>
                                    <select v-model="sectionForm.subject_id" required
                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm">
                                        <option value="">Fənn seçin</option>
                                        <option v-for="subject in subjects" :key="subject.id" :value="subject.id">
                                            {{ subject.name }}
                                        </option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-600">Sual sayı</label>
                                    <input v-model="sectionForm.question_count" type="number" min="1"
                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm" />
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-600">Maks. bal</label>
                                    <input v-model="sectionForm.max_score" type="number" min="1" step="0.01"
                                        placeholder="qrupdan"
                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm" />
                                </div>
                                <button type="submit" :disabled="sectionForm.processing"
                                    class="px-4 py-2 bg-indigo-600 text-white text-sm rounded-lg hover:bg-indigo-700 disabled:opacity-40">
                                    Bölmə əlavə et
                                </button>
                                <p v-if="sectionForm.errors.subject_id" class="sm:col-span-5 text-sm text-red-600">
                                    {{ sectionForm.errors.subject_id }}
                                </p>
                                <p v-if="$page.props.errors.section" class="sm:col-span-5 text-sm text-red-600">
                                    {{ $page.props.errors.section }}
                                </p>
                            </form>

                            <div v-for="section in sections" :key="section.id" class="mb-8">
                                <div class="flex flex-wrap items-center justify-between gap-2 border-b border-gray-200 pb-2 mb-3">
                                    <h4 class="font-semibold text-gray-900">
                                        {{ section.title }}
                                        <span class="ml-2 text-sm font-normal text-gray-500">
                                            {{ section.questions.length }}<template v-if="section.question_count">/{{ section.question_count }}</template> sual
                                            <template v-if="section.max_score"> · maks. {{ section.max_score }} bal</template>
                                        </span>
                                    </h4>
                                    <div class="flex items-center gap-2">
                                        <Link :href="route('admin.exams.questions.create', { exam: exam.id, section_id: section.id })"
                                            class="px-3 py-1.5 bg-indigo-600 text-white text-xs rounded hover:bg-indigo-700">
                                            + Sual
                                        </Link>
                                        <Link :href="route('admin.questions.index', { subject_id: exam.subject_id, exam_id: exam.id, section_id: section.id })"
                                            class="px-3 py-1.5 bg-gray-100 text-gray-800 text-xs rounded hover:bg-gray-200">
                                            Bankdan
                                        </Link>
                                        <button v-if="sections.length > 1 && !section.questions.length" type="button"
                                            @click="removeSection(section)"
                                            class="text-xs text-red-600 hover:text-red-800">Bölməni sil</button>
                                    </div>
                                </div>

                                <p v-if="!section.questions.length" class="text-sm text-gray-500">
                                    Bu bölmədə hələ sual yoxdur.
                                </p>

                                <div v-else class="space-y-4">
                                    <div v-for="(question, index) in section.questions" :key="question.id"
                                        class="p-4 bg-gray-50 rounded-lg">
                                        <div class="flex items-start gap-3">
                                            <span class="px-2 py-1 bg-indigo-100 text-indigo-800 text-sm rounded">
                                                {{ index + 1 }}
                                            </span>
                                            <div class="flex-1">
                                                <div class="flex items-start justify-between gap-3">
                                                    <MathText :text="question.question_text" class="text-gray-900 font-medium" />
                                                    <span class="flex-shrink-0 text-right">
                                                        <span class="px-2 py-0.5 bg-gray-200 text-gray-700 text-xs rounded">
                                                            {{ QUESTION_TYPE_LABELS[question.type] ?? question.type }}
                                                        </span>
                                                        <span v-if="question.topic" class="block mt-1 text-xs text-gray-500">
                                                            {{ question.topic }}
                                                        </span>
                                                    </span>
                                                </div>

                                                <img v-if="question.question_image" :src="`/storage/${question.question_image}`"
                                                    alt="Sual şəkli" class="mt-3 max-w-xs rounded border border-gray-200" />

                                                <div v-if="question.options?.length" class="mt-3 space-y-2">
                                                    <div v-for="option in question.options" :key="option.id"
                                                        :class="['p-2 rounded text-sm', option.is_correct
                                                            ? 'bg-green-100 text-green-800 border border-green-300'
                                                            : 'bg-white border border-gray-200']">
                                                        <span class="font-medium mr-1">{{ option.option_letter }})</span>
                                                        <MathText :text="option.option_text" class="inline" />
                                                        <span v-if="option.is_correct" class="ml-2 text-xs">(Düzgün)</span>
                                                    </div>
                                                </div>

                                                <p v-if="question.type === 'open_coded' && question.accepted_answers?.length"
                                                    class="mt-3 text-sm text-gray-700">
                                                    Düzgün cavab:
                                                    <code class="bg-white border border-gray-200 rounded px-1">
                                                        {{ question.accepted_answers.join(' , ') }}
                                                    </code>
                                                </p>

                                                <p v-if="question.explanation" class="mt-2 text-sm text-gray-500">
                                                    İzah: {{ question.explanation }}
                                                </p>
                                            </div>

                                            <div class="flex flex-shrink-0 items-center gap-1">
                                                <button type="button" @click="moveQuestion(question, 'up')"
                                                    :disabled="index === 0"
                                                    class="px-2 py-1 text-sm border border-gray-300 rounded disabled:opacity-30 hover:bg-white"
                                                    title="Yuxarı">↑</button>
                                                <button type="button" @click="moveQuestion(question, 'down')"
                                                    :disabled="index === section.questions.length - 1"
                                                    class="px-2 py-1 text-sm border border-gray-300 rounded disabled:opacity-30 hover:bg-white"
                                                    title="Aşağı">↓</button>
                                                <button v-if="!exam.is_published" type="button"
                                                    @click="replaceQuestion(question)"
                                                    class="px-2 py-1 text-sm text-gray-600 hover:text-gray-900"
                                                    title="Bankdan başqa təsadüfi sualla əvəz et">Əvəz et</button>
                                                <Link :href="route('admin.exams.questions.edit', [exam.id, question.id])"
                                                    class="px-2 py-1 text-sm text-indigo-600 hover:text-indigo-800">Redaktə</Link>
                                                <button type="button" @click="detachQuestion(question)"
                                                    class="px-2 py-1 text-sm text-red-600 hover:text-red-800"
                                                    title="Bankdan silinmir, yalnız bu imtahandan ayrılır">Ayır</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

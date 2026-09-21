<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import MathText from '@/Components/MathText.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    questions: { type: Object, required: true },
    subjects: { type: Array, default: () => [] },
    topics: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
    // İmtahan səhifəsindən gəlibsə: hər sətirdə "əlavə et" düyməsi
    targetExam: { type: Object, default: null },
});

const TYPE_LABELS = {
    multiple_choice: 'Test',
    open_coded: 'Qısa cavab',
    open_written: 'Açıq',
};

const DIFFICULTY_LABELS = { easy: 'Sadə', medium: 'Orta', hard: 'Mürəkkəb' };

const LANGUAGE_LABELS = { az: 'Az', ru: 'Ru' };

const subjectId = ref(props.filters.subject_id ?? '');
const topicId = ref(props.filters.topic_id ?? '');
const type = ref(props.filters.type ?? '');
const difficulty = ref(props.filters.difficulty ?? '');
const search = ref(props.filters.search ?? '');
// Sual dili = tədris sektoru. Hədəf imtahan varsa dil onun sektoruna bərkidilir.
const language = ref(props.filters.language ?? '');

// Fənn seçiləndə yalnız onun mövzuları göstərilir
const visibleTopics = computed(() => (subjectId.value
    ? props.topics.filter((topic) => String(topic.subject_id) === String(subjectId.value))
    : props.topics));

const apply = () => {
    router.get(route('admin.questions.index'), Object.fromEntries(
        Object.entries({
            subject_id: subjectId.value,
            topic_id: topicId.value,
            type: type.value,
            difficulty: difficulty.value,
            language: language.value,
            search: search.value,
            exam_id: props.targetExam?.id ?? '',
        }).filter(([, value]) => value !== '' && value !== null)
    ), { preserveState: true, replace: true });
};

const attach = (question) => {
    router.post(route('admin.exams.questions.attach', props.targetExam.id),
        { question_id: question.id, section_id: props.filters.section_id ?? null },
        { preserveScroll: true, preserveState: false });
};

const alreadyInExam = (question) => props.targetExam?.question_ids?.includes(question.id) ?? false;

const remove = (question) => {
    if (confirm('Sual bankdan silinsin? Bu əməliyyat geri qaytarılmır.')) {
        router.delete(route('admin.questions.destroy', question.id), { preserveScroll: true });
    }
};
</script>

<template>
    <Head title="Sual bankı" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">
                    Sual bankı
                    <span v-if="targetExam" class="ml-2 text-sm font-normal text-gray-500">
                        → {{ targetExam.title }} imtahanına əlavə et
                    </span>
                </h2>
                <Link :href="route('admin.topics.index')" class="text-sm text-indigo-600 hover:text-indigo-800">
                    Mövzular
                </Link>
            </div>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8 space-y-6">
                <div class="bg-white shadow-sm rounded-lg p-4 flex flex-wrap gap-3 items-center">
                    <select v-model="subjectId" @change="topicId = ''; apply()"
                        class="rounded-md border-gray-300 shadow-sm text-sm">
                        <option value="">Bütün fənlər</option>
                        <option v-for="subject in subjects" :key="subject.id" :value="subject.id">{{ subject.name }}</option>
                    </select>

                    <select v-model="topicId" @change="apply" class="rounded-md border-gray-300 shadow-sm text-sm">
                        <option value="">Bütün mövzular</option>
                        <option v-for="topic in visibleTopics" :key="topic.id" :value="topic.id">{{ topic.name }}</option>
                    </select>

                    <select v-model="type" @change="apply" class="rounded-md border-gray-300 shadow-sm text-sm">
                        <option value="">Bütün tiplər</option>
                        <option value="multiple_choice">Test</option>
                        <option value="open_coded">Qısa cavab</option>
                        <option value="open_written">Açıq</option>
                    </select>

                    <select v-model="difficulty" @change="apply" class="rounded-md border-gray-300 shadow-sm text-sm">
                        <option value="">Bütün çətinliklər</option>
                        <option value="easy">Sadə</option>
                        <option value="medium">Orta</option>
                        <option value="hard">Mürəkkəb</option>
                    </select>

                    <select v-if="!targetExam" v-model="language" @change="apply"
                        class="rounded-md border-gray-300 shadow-sm text-sm">
                        <option value="">Hər iki dil</option>
                        <option value="az">Azərbaycan dilində</option>
                        <option value="ru">Rus dilində</option>
                    </select>
                    <span v-else class="text-xs text-gray-500">
                        Yalnız {{ LANGUAGE_LABELS[targetExam.sector] }} dilində suallar
                        (imtahan sektoru: {{ LANGUAGE_LABELS[targetExam.sector] }})
                    </span>

                    <form @submit.prevent="apply" class="flex items-center gap-2">
                        <TextInput v-model="search" type="search" placeholder="Sual mətnində axtar" class="text-sm" />
                    </form>
                </div>

                <div class="bg-white shadow-sm rounded-lg overflow-x-auto">
                    <p v-if="!questions.data.length" class="p-6 text-sm text-gray-500">Sual tapılmadı.</p>

                    <table v-else class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-2 text-left font-medium text-gray-500">Sual</th>
                                <th class="px-4 py-2 text-left font-medium text-gray-500">Fənn / mövzu</th>
                                <th class="px-4 py-2 text-left font-medium text-gray-500">Tip</th>
                                <th class="px-4 py-2 text-left font-medium text-gray-500">Dil</th>
                                <th class="px-4 py-2 text-left font-medium text-gray-500">İmtahan</th>
                                <th class="px-4 py-2 text-left font-medium text-gray-500">Cəhd</th>
                                <th class="px-4 py-2"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="question in questions.data" :key="question.id">
                                <td class="px-4 py-2 max-w-md">
                                    <MathText :text="question.question_text" />
                                </td>
                                <td class="px-4 py-2">
                                    {{ question.subject }}
                                    <div v-if="question.topic" class="text-xs text-gray-500">{{ question.topic }}</div>
                                </td>
                                <td class="px-4 py-2 whitespace-nowrap">
                                    {{ TYPE_LABELS[question.type] ?? question.type }}
                                    <div class="text-xs text-gray-500">{{ DIFFICULTY_LABELS[question.difficulty] }}</div>
                                </td>
                                <td class="px-4 py-2">{{ LANGUAGE_LABELS[question.language] ?? question.language }}</td>
                                <td class="px-4 py-2">{{ question.exams_count }}</td>
                                <td class="px-4 py-2">{{ question.attempt_usage }}</td>
                                <td class="px-4 py-2 text-right whitespace-nowrap">
                                    <template v-if="targetExam">
                                        <span v-if="alreadyInExam(question)" class="text-xs text-gray-400">
                                            imtahandadır
                                        </span>
                                        <button v-else type="button" @click="attach(question)"
                                            class="px-3 py-1 bg-indigo-600 text-white text-xs rounded hover:bg-indigo-700">
                                            Əlavə et
                                        </button>
                                    </template>
                                    <template v-else>
                                        <button v-if="!question.attempt_usage" type="button" @click="remove(question)"
                                            class="text-red-600 hover:text-red-800">Sil</button>
                                        <span v-else class="text-xs text-gray-400" title="Cəhdlərdə istifadə olunub">
                                            silinmir
                                        </span>
                                    </template>
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <div v-if="questions.links?.length > 3" class="px-4 py-3 border-t border-gray-200 flex flex-wrap gap-2">
                        <Link v-for="link in questions.links" :key="link.label" :href="link.url"
                            :class="['px-3 py-1 text-sm rounded',
                                link.active ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200',
                                !link.url && 'opacity-50 pointer-events-none']"
                            v-html="link.label" />
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

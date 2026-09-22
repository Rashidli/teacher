<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import Checkbox from '@/Components/Checkbox.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

// Yeni hesab yaradılmır: mövcud hesaba müəllim rolu və fənlər əlavə olunur
const props = defineProps({
    subjects: {
        type: Array,
        default: () => [],
    },
    alreadyTeacher: {
        type: Boolean,
        default: false,
    },
});

const form = useForm({
    subjects: [],
});

const toggleSubject = (subjectId) => {
    const index = form.subjects.indexOf(subjectId);
    if (index > -1) {
        form.subjects.splice(index, 1);
    } else {
        form.subjects.push(subjectId);
    }
};

const humanitarianSubjects = computed(() =>
    props.subjects.filter(s => s.category === 'humanitarian')
);

const technicalSubjects = computed(() =>
    props.subjects.filter(s => s.category === 'technical')
);

const submit = () => {
    form.post(route('teacher.register'));
};
</script>

<template>
    <Head title="Müəllim ol" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">Müəllim ol</h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-2xl sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-sm rounded-lg p-6">
                    <p class="text-sm text-gray-600">
                        Mövcud hesabınıza müəllim rolu əlavə olunur — yeni hesab yaradılmır.
                        Şagird kabinetiniz olduğu kimi qalır.
                    </p>

                    <p v-if="alreadyTeacher" class="mt-3 rounded-lg bg-blue-50 p-3 text-sm text-blue-800">
                        Hesabınızın artıq müəllim rolu var. Aşağıdan fənlərinizi genişləndirə bilərsiniz.
                    </p>

                    <form @submit.prevent="submit" class="mt-6">
                        <div>
                            <InputLabel value="Fənlər (ən az 1 fənn seçin)" />
                            <p class="text-sm text-gray-500 mb-3">Hansı fənlərdən imtahan yaratmaq istəyirsiniz?</p>

                            <div class="border border-gray-200 rounded-lg overflow-hidden">
                                <!-- Humanitar fənlər -->
                                <div class="bg-blue-50 px-3 py-2 border-b border-gray-200">
                                    <span class="text-xs font-semibold text-blue-700 uppercase tracking-wide">Humanitar fənlər</span>
                                </div>
                                <div class="grid grid-cols-2 gap-1 p-3 border-b border-gray-200">
                                    <label
                                        v-for="subject in humanitarianSubjects"
                                        :key="subject.id"
                                        class="flex items-center p-2 rounded hover:bg-gray-50 cursor-pointer"
                                    >
                                        <Checkbox
                                            :checked="form.subjects.includes(subject.id)"
                                            @update:checked="toggleSubject(subject.id)"
                                        />
                                        <span class="ml-2 text-sm text-gray-700">{{ subject.name }}</span>
                                    </label>
                                </div>

                                <!-- Texniki fənlər -->
                                <div class="bg-green-50 px-3 py-2 border-b border-gray-200">
                                    <span class="text-xs font-semibold text-green-700 uppercase tracking-wide">Texniki fənlər</span>
                                </div>
                                <div class="grid grid-cols-2 gap-1 p-3">
                                    <label
                                        v-for="subject in technicalSubjects"
                                        :key="subject.id"
                                        class="flex items-center p-2 rounded hover:bg-gray-50 cursor-pointer"
                                    >
                                        <Checkbox
                                            :checked="form.subjects.includes(subject.id)"
                                            @update:checked="toggleSubject(subject.id)"
                                        />
                                        <span class="ml-2 text-sm text-gray-700">{{ subject.name }}</span>
                                    </label>
                                </div>
                            </div>
                            <InputError class="mt-2" :message="form.errors.subjects" />
                        </div>

                        <div class="mt-6">
                            <PrimaryButton
                                class="w-full justify-center"
                                :class="{ 'opacity-25': form.processing }"
                                :disabled="form.processing"
                            >
                                Müəllim rolunu əlavə et
                            </PrimaryButton>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

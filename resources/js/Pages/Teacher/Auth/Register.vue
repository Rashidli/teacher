<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import Checkbox from '@/Components/Checkbox.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    subjects: {
        type: Array,
        default: () => [],
    },
});

const form = useForm({
    first_name: '',
    last_name: '',
    email: '',
    phone: '',
    password: '',
    password_confirmation: '',
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
    form.post(route('teacher.register'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <GuestLayout>
        <Head title="Müəllim Qeydiyyatı" />

        <div class="mb-6 text-center">
            <h2 class="text-2xl font-bold text-gray-900">Müəllim Qeydiyyatı</h2>
            <p class="text-sm text-gray-600 mt-1">İmtahan yaratmaq üçün qeydiyyatdan keçin</p>
        </div>

        <form @submit.prevent="submit">
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <InputLabel for="first_name" value="Ad" />
                    <TextInput
                        id="first_name"
                        type="text"
                        class="mt-1 block w-full"
                        v-model="form.first_name"
                        required
                        autofocus
                    />
                    <InputError class="mt-2" :message="form.errors.first_name" />
                </div>

                <div>
                    <InputLabel for="last_name" value="Soyad" />
                    <TextInput
                        id="last_name"
                        type="text"
                        class="mt-1 block w-full"
                        v-model="form.last_name"
                        required
                    />
                    <InputError class="mt-2" :message="form.errors.last_name" />
                </div>
            </div>

            <div class="mt-4">
                <InputLabel for="email" value="E-poçt" />
                <TextInput
                    id="email"
                    type="email"
                    class="mt-1 block w-full"
                    v-model="form.email"
                    required
                    autocomplete="username"
                />
                <InputError class="mt-2" :message="form.errors.email" />
            </div>

            <div class="mt-4">
                <InputLabel for="phone" value="Telefon (istəyə bağlı)" />
                <TextInput
                    id="phone"
                    type="tel"
                    class="mt-1 block w-full"
                    v-model="form.phone"
                    placeholder="+994 XX XXX XX XX"
                />
                <InputError class="mt-2" :message="form.errors.phone" />
            </div>

            <div class="mt-4">
                <InputLabel for="password" value="Şifrə" />
                <TextInput
                    id="password"
                    type="password"
                    class="mt-1 block w-full"
                    v-model="form.password"
                    required
                    autocomplete="new-password"
                />
                <InputError class="mt-2" :message="form.errors.password" />
            </div>

            <div class="mt-4">
                <InputLabel for="password_confirmation" value="Şifrəni təsdiqlə" />
                <TextInput
                    id="password_confirmation"
                    type="password"
                    class="mt-1 block w-full"
                    v-model="form.password_confirmation"
                    required
                    autocomplete="new-password"
                />
                <InputError class="mt-2" :message="form.errors.password_confirmation" />
            </div>

            <!-- Subject Selection -->
            <div class="mt-6">
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

            <div class="mt-4 p-3 bg-yellow-50 border border-yellow-200 rounded-lg">
                <p class="text-sm text-yellow-800">
                    <strong>Qeyd:</strong> Müəllim hesabları admin tərəfindən təsdiq edildikdən sonra aktiv olur.
                </p>
            </div>

            <div class="mt-6">
                <PrimaryButton
                    class="w-full justify-center"
                    :class="{ 'opacity-25': form.processing }"
                    :disabled="form.processing"
                >
                    Qeydiyyat
                </PrimaryButton>
            </div>

            <div class="mt-6 text-center">
                <span class="text-sm text-gray-600">Artıq hesabınız var?</span>
                <Link
                    :href="route('teacher.login')"
                    class="ms-1 text-sm text-indigo-600 hover:text-indigo-800 underline"
                >
                    Daxil olun
                </Link>
            </div>
        </form>
    </GuestLayout>
</template>

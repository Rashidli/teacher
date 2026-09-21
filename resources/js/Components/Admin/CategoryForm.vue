<script setup>
/* Kateqoriya forması — yaratma və redaktə səhifələri eyni komponenti işlədir. */
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { Link } from '@inertiajs/vue3';

defineProps({
    form: { type: Object, required: true },
    parents: { type: Array, default: () => [] },
    groups: { type: Array, default: () => [] },
    submitLabel: { type: String, default: 'Yadda saxla' },
});

const emit = defineEmits(['submit']);
</script>

<template>
    <form @submit.prevent="emit('submit')" class="bg-white shadow-sm rounded-lg p-6 space-y-6">
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <InputLabel for="parent_id" value="Valideyn kateqoriya" />
                <select
                    id="parent_id"
                    v-model="form.parent_id"
                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                >
                    <option :value="null">— kök kateqoriya —</option>
                    <option v-for="parent in parents" :key="parent.id" :value="parent.id">{{ parent.label }}</option>
                </select>
                <InputError :message="form.errors.parent_id" class="mt-2" />
            </div>

            <div>
                <InputLabel for="group_id" value="Bal qrupu (yalnız abituriyent düyünlərində)" />
                <select
                    id="group_id"
                    v-model="form.group_id"
                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                >
                    <option :value="null">— yoxdur —</option>
                    <option v-for="group in groups" :key="group.id" :value="group.id">
                        {{ group.code }} — {{ group.name }}
                    </option>
                </select>
                <p class="mt-1 text-xs text-gray-500">
                    Qrup seçiləndə maksimal ballar bal matrisindən götürülür.
                </p>
                <InputError :message="form.errors.group_id" class="mt-2" />
            </div>

            <div>
                <InputLabel for="name" value="Ad" />
                <TextInput id="name" v-model="form.name" type="text" class="mt-1 block w-full" required />
                <InputError :message="form.errors.name" class="mt-2" />
            </div>

            <div>
                <InputLabel for="slug" value="Slug (URL hissəsi)" />
                <TextInput
                    id="slug"
                    v-model="form.slug"
                    type="text"
                    class="mt-1 block w-full font-mono text-sm"
                    required
                    placeholder="1-ci-qrup"
                />
                <p class="mt-1 text-xs text-gray-500">Kiçik latın hərfləri, rəqəm və defis.</p>
                <InputError :message="form.errors.slug" class="mt-2" />
            </div>
        </div>

        <div>
            <InputLabel for="short" value="Qısa mətn (kartlarda görünür)" />
            <TextInput id="short" v-model="form.short" type="text" class="mt-1 block w-full" />
            <InputError :message="form.errors.short" class="mt-2" />
        </div>

        <div>
            <InputLabel for="description" value="Təsvir" />
            <textarea
                id="description"
                v-model="form.description"
                rows="3"
                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
            ></textarea>
            <InputError :message="form.errors.description" class="mt-2" />
        </div>

        <div class="grid gap-4 sm:grid-cols-3">
            <label class="flex items-center gap-2">
                <input
                    v-model="form.is_active"
                    type="checkbox"
                    class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500"
                />
                <span class="text-sm text-gray-700">Aktiv</span>
            </label>
            <label class="flex items-center gap-2">
                <input
                    v-model="form.has_exams"
                    type="checkbox"
                    class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500"
                />
                <span class="text-sm text-gray-700">İmtahanı var</span>
            </label>
            <div>
                <InputLabel for="order" value="Sıra" />
                <TextInput id="order" v-model="form.order" type="number" min="0" class="mt-1 block w-full" />
            </div>
        </div>

        <fieldset class="rounded-md border border-gray-200 p-4 space-y-4">
            <legend class="px-1 text-sm font-medium text-gray-700">SEO</legend>

            <div>
                <InputLabel for="seo_title" value="Title" />
                <TextInput id="seo_title" v-model="form.seo_title" type="text" class="mt-1 block w-full" />
            </div>
            <div>
                <InputLabel for="seo_description" value="Meta description" />
                <textarea
                    id="seo_description"
                    v-model="form.seo_description"
                    rows="2"
                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm"
                ></textarea>
            </div>
            <div>
                <InputLabel for="h1" value="H1 (boşdursa ad işlədilir)" />
                <TextInput id="h1" v-model="form.h1" type="text" class="mt-1 block w-full" />
            </div>
            <div>
                <InputLabel for="intro" value="Giriş mətni" />
                <textarea
                    id="intro"
                    v-model="form.intro"
                    rows="4"
                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm"
                ></textarea>
            </div>
        </fieldset>

        <div class="flex justify-end gap-4 border-t border-gray-200 pt-4">
            <Link :href="route('admin.categories.index')" class="px-4 py-2 text-gray-700 hover:text-gray-900">
                Ləğv et
            </Link>
            <PrimaryButton :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                {{ submitLabel }}
            </PrimaryButton>
        </div>
    </form>
</template>

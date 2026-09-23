<script setup>
import { computed } from 'vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';

/**
 * Etiket seçimi — imtahan formasında və bankdan generasiyada eyni komponent.
 *
 * Sinif etiketləri ayrıca qrupda göstərilir: kataloqda ən çox işlənən filtr onlardır.
 */
const props = defineProps({
    // Seçilmiş id-lər (v-model)
    modelValue: { type: Array, default: () => [] },
    tags: { type: Array, default: () => [] },
    error: { type: String, default: null },
});

const emit = defineEmits(['update:modelValue']);

const grades = computed(() => props.tags.filter((tag) => tag.kind === 'grade'));
const others = computed(() => props.tags.filter((tag) => tag.kind !== 'grade'));

const toggle = (id) => {
    const next = props.modelValue.includes(id)
        ? props.modelValue.filter((value) => value !== id)
        : [...props.modelValue, id];

    emit('update:modelValue', next);
};
</script>

<template>
    <div v-if="tags.length">
        <InputLabel value="Etiketlər" />
        <p class="mt-1 text-xs text-gray-500">
            Kateqoriyadan ayrıdır: kateqoriya imtahanın növünü, etiket isə sinif səviyyəsini
            və digər əlamətləri bildirir. Kataloqda ayrıca filtr kimi görünür.
        </p>

        <div v-if="grades.length" class="mt-3">
            <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Sinif</p>
            <div class="mt-2 flex flex-wrap gap-2">
                <button
                    v-for="tag in grades"
                    :key="tag.id"
                    type="button"
                    :aria-pressed="modelValue.includes(tag.id)"
                    :class="[
                        'min-h-[44px] rounded-full border px-4 text-sm font-medium transition',
                        modelValue.includes(tag.id)
                            ? 'border-indigo-600 bg-indigo-600 text-white'
                            : 'border-gray-300 bg-white text-gray-700 hover:border-indigo-400',
                    ]"
                    @click="toggle(tag.id)"
                >{{ tag.name }}</button>
            </div>
        </div>

        <div v-if="others.length" class="mt-3">
            <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Digər</p>
            <div class="mt-2 flex flex-wrap gap-2">
                <button
                    v-for="tag in others"
                    :key="tag.id"
                    type="button"
                    :aria-pressed="modelValue.includes(tag.id)"
                    :class="[
                        'min-h-[44px] rounded-full border px-4 text-sm font-medium transition',
                        modelValue.includes(tag.id)
                            ? 'border-indigo-600 bg-indigo-600 text-white'
                            : 'border-gray-300 bg-white text-gray-700 hover:border-indigo-400',
                    ]"
                    @click="toggle(tag.id)"
                >{{ tag.name }}</button>
            </div>
        </div>

        <InputError :message="error" class="mt-2" />
    </div>
</template>

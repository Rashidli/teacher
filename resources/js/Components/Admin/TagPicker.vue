<script setup>
import { computed } from 'vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';

/**
 * Etiket seçimi — imtahan formasında və bankdan generasiyada eyni komponent.
 *
 * Sinif etiketləri ayrıca qrupda göstərilir: kataloqda ən çox işlənən filtr onlardır.
 *
 * QAYDA: sinif etiketi yalnız kateqoriya adı sinfi göstərməyəndə işlədilir. Kateqoriya
 * onsuz da sinif bildirirsə ("9-cu sinif buraxılış"), seçim BLOKLANMIR — sadəcə
 * xəbərdarlıq çıxır, çünki istisna hallar ola bilər.
 */
const props = defineProps({
    // Seçilmiş id-lər (v-model)
    modelValue: { type: Array, default: () => [] },
    tags: { type: Array, default: () => [] },
    error: { type: String, default: null },
    // Seçilmiş kateqoriyanın adında sinif varmı (serverdən gələn `mentions_grade`)
    categoryMentionsGrade: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue']);

const grades = computed(() => props.tags.filter((tag) => tag.kind === 'grade'));
const others = computed(() => props.tags.filter((tag) => tag.kind !== 'grade'));

// Xəbərdarlıq yalnız ziddiyyət yarananda: kateqoriya sinif deyir, üstəlik etiket də seçilib
const gradeConflict = computed(() => props.categoryMentionsGrade
    && grades.value.some((tag) => props.modelValue.includes(tag.id)));

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
            <p class="mt-1 text-xs text-gray-500">
                Yalnız kateqoriya adında sinif göstərilməyəndə işlədilir.
            </p>
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

            <!-- Bloklamır: qeyri-adi hallarda admin bilərək seçə bilər -->
            <p v-if="gradeConflict" role="status" class="mt-2 rounded-md bg-amber-50 p-2 text-xs text-amber-900">
                Seçilmiş kateqoriyanın adında onsuz da sinif var. Sinif etiketi burada təkrar
                olur və kataloqda göstərilmir — adətən ona ehtiyac yoxdur.
            </p>
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

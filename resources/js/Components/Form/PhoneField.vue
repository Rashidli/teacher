<script setup>
import { computed, ref } from 'vue';
import FieldError from './FieldError.vue';

/**
 * Azərbaycan mobil nömrəsi: "+994" sabit prefiks, istifadəçi yalnız 9 rəqəm yazır,
 * görüntü "XX XXX XX XX". v-model 9 rəqəmdir ("501234567").
 * Yapışdırılan mətndən hərf/boşluq silinir; "+994", "994" və ya yerli "0" prefiksi atılır.
 * Backend (RegisterRequest) eyni təmizləmə və regex yoxlamasını təkrar edir.
 */
const model = defineModel({ type: String, default: '' });

const props = defineProps({
    id: { type: String, required: true },
    label: { type: String, required: true },
    operatorCodes: { type: Array, default: () => ['10', '50', '51', '55', '60', '70', '77', '99'] },
    error: { type: String, default: '' },
    hint: { type: String, default: '' },
    invalidMessage: { type: String, default: '' },
    required: { type: Boolean, default: false },
});

const touched = ref(false);

function onlyDigits(value) {
    let digits = String(value ?? '').replace(/\D+/g, '');

    // Ardıcıl: əvvəl ölkə kodu, sonra yerli "0" ("+994 (055) ..." → "55...")
    if (digits.startsWith('994') && digits.length >= 12) {
        digits = digits.slice(3);
    }
    if (digits.startsWith('0') && digits.length >= 10) {
        digits = digits.slice(1);
    }

    return digits.slice(0, 9);
}

// "501234567" → "50 123 45 67"
function format(digits) {
    const parts = [digits.slice(0, 2), digits.slice(2, 5), digits.slice(5, 7), digits.slice(7, 9)];

    return parts.filter(Boolean).join(' ');
}

const display = computed(() => format(model.value));

// Kursor: yazılan rəqəmdən sonra qalsın (formatlama boşluq əlavə etsə belə)
function onInput(event) {
    const input = event.target;
    const caret = input.selectionStart ?? input.value.length;
    const digitsBeforeCaret = onlyDigits(input.value.slice(0, caret)).length;

    model.value = onlyDigits(input.value);

    const formatted = format(model.value);
    input.value = formatted;

    let position = 0;
    let seen = 0;
    while (position < formatted.length && seen < digitsBeforeCaret) {
        if (/\d/.test(formatted[position])) {
            seen++;
        }
        position++;
    }
    input.setSelectionRange(position, position);
}

// Tam nömrə yapışdırılanda (+994 50 123 45 67, 050-123-45-67 və s.)
function onPaste(event) {
    const text = event.clipboardData?.getData('text');
    if (!text) {
        return;
    }
    event.preventDefault();
    model.value = onlyDigits(text);
    event.target.value = format(model.value);
}

// Brauzerdə dərhal yoxlama (yalnız sahədən çıxandan sonra), əsas yoxlama backend-dədir
const clientError = computed(() => {
    if (!touched.value || model.value === '') {
        return '';
    }
    const complete = model.value.length === 9;
    const validOperator = props.operatorCodes.includes(model.value.slice(0, 2));

    return complete && validOperator ? '' : props.invalidMessage;
});

const shownError = computed(() => props.error || clientError.value);
</script>

<template>
    <div class="field">
        <label :for="id" class="field-label">{{ label }}</label>
        <div class="phone" :class="{ 'phone--error': shownError }">
            <span class="phone-prefix" aria-hidden="true">+994</span>
            <input
                :id="id"
                :value="display"
                type="tel"
                :name="id"
                inputmode="numeric"
                autocomplete="tel-national"
                placeholder="50 123 45 67"
                maxlength="12"
                :required="required"
                class="phone-input"
                :aria-invalid="shownError ? 'true' : undefined"
                :aria-describedby="[`${id}-prefix`, hint ? `${id}-hint` : null, shownError ? `${id}-error` : null].filter(Boolean).join(' ')"
                @input="onInput"
                @paste="onPaste"
                @blur="touched = true"
            />
        </div>
        <span :id="`${id}-prefix`" class="visually-hidden">+994</span>
        <p v-if="hint" :id="`${id}-hint`" class="field-hint">{{ hint }}</p>
        <FieldError :id="`${id}-error`" :message="shownError" />
    </div>
</template>

<style scoped>
.field-label {
    display: block;
    margin-bottom: 6px;
    font-size: 1rem;
    font-weight: 600;
}

.phone {
    display: flex;
    align-items: stretch;
    min-height: 48px;
    border: 1.5px solid #C7CBD1;
    border-radius: 6px;
    background: #fff;
}

.phone:hover {
    border-color: #9EA4AC;
}

.phone:focus-within {
    border-color: var(--pen);
    outline: 2px solid var(--pen);
    outline-offset: 1px;
}

.phone--error,
.phone--error:hover {
    border-color: var(--ink-red);
}

/* Sabit prefiks: cavab kartındakı çap olunmuş xana kimi */
.phone-prefix {
    display: grid;
    place-items: center;
    flex: none;
    padding-inline: 12px;
    border-right: 1px solid var(--ink-red-line);
    border-radius: 6px 0 0 6px;
    background: var(--paper-sunk);
    color: var(--graphite);
    font-weight: 600;
    font-variant-numeric: tabular-nums;
}

.phone-input {
    flex: 1;
    min-width: 0;
    padding: 10px 12px;
    border: 0;
    border-radius: 0 6px 6px 0;
    background: transparent;
    color: var(--graphite);
    font: inherit;
    font-size: 1rem;
    font-variant-numeric: tabular-nums;
    letter-spacing: 0.02em;
}

.phone-input:focus {
    outline: none;
    box-shadow: none;
}

.phone-input::placeholder {
    color: #A3A8AF;
}

.field-hint {
    margin: 6px 0 0;
    color: var(--muted);
    font-size: 0.9375rem;
    line-height: 1.4;
}
</style>

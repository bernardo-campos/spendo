<script setup>
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';

const props = defineProps({
    amount: {
        type: [Number, String],
        default: '',
    },
    formatAmount: {
        type: Function,
        required: true,
    },
    open: {
        type: Boolean,
        required: true,
    },
});

const emit = defineEmits(['close', 'update:amount']);

const amountDraft = ref('');
const expressionTokens = ref([]);
const amountEditorStyle = ref({});
const amountEditorInputRef = ref(null);
let previousBodyOverflow = '';
let previousDocumentOverflow = '';

const normalizeAmount = (value) => {
    const sanitizedValue = String(value ?? '').replace(/[^\d,.]/g, '');

    if (sanitizedValue === '') {
        return '';
    }

    const separatorIndex = Math.max(sanitizedValue.lastIndexOf(','), sanitizedValue.lastIndexOf('.'));

    if (separatorIndex === -1) {
        return sanitizedValue;
    }

    const integerPart = sanitizedValue.slice(0, separatorIndex).replace(/[.,]/g, '') || '0';
    const decimalPart = sanitizedValue.slice(separatorIndex + 1).replace(/[.,]/g, '');

    return `${integerPart}.${decimalPart}`;
};

const operatorButtons = [
    { value: '+', label: '+' },
    { value: '-', label: '−' },
    { value: '*', label: '×' },
    { value: '/', label: '÷' },
];

const isOperator = (value) => operatorButtons.some((operator) => operator.value === value);

const formatExpressionValue = (value) => {
    const normalizedAmount = normalizeAmount(value);

    return normalizedAmount === ''
        ? ''
        : Number(normalizedAmount).toLocaleString('es-AR', { maximumFractionDigits: 2 });
};

const expressionDisplay = computed(() => [...expressionTokens.value, amountDraft.value]
    .filter((value, index, values) => value !== '' || index === values.length - 1)
    .map((value) => {
        const operator = operatorButtons.find((item) => item.value === value);

        return operator ? operator.label : formatExpressionValue(value);
    })
    .filter(Boolean)
    .join(' '));

const calculationParts = computed(() => {
    const parts = [...expressionTokens.value];
    const normalizedDraft = normalizeAmount(amountDraft.value);

    if (normalizedDraft !== '') {
        parts.push(normalizedDraft);
    }

    if (isOperator(parts.at(-1))) {
        parts.pop();
    }

    return parts;
});

const totalValue = computed(() => {
    const parts = calculationParts.value;

    if (parts.length === 0) {
        return null;
    }

    let term = Number(parts[0]);
    let additiveTotal = 0;
    let additiveOperator = '+';

    for (let index = 1; index < parts.length; index += 2) {
        const operator = parts[index];
        const nextValue = Number(parts[index + 1]);

        if (!Number.isFinite(nextValue)) {
            return null;
        }

        if (operator === '*' || operator === '/') {
            if (operator === '/' && nextValue === 0) {
                return null;
            }

            term = operator === '*' ? term * nextValue : term / nextValue;

            continue;
        }

        additiveTotal += additiveOperator === '+' ? term : -term;
        additiveOperator = operator;
        term = nextValue;
    }

    const total = additiveTotal + (additiveOperator === '+' ? term : -term);

    return Number.isFinite(total) ? total : null;
});

const hasCalculationInput = computed(() => calculationParts.value.length > 0);

const formattedAmountDraft = computed(() => {
    const normalizedAmount = normalizeAmount(amountDraft.value);

    return normalizedAmount === '' ? '0,00' : props.formatAmount(Number(normalizedAmount));
});

const formattedTotal = computed(() => totalValue.value === null
    ? '0,00'
    : props.formatAmount(totalValue.value));

const updateAmountEditorViewport = () => {
    const viewport = window.visualViewport;

    amountEditorStyle.value = {
        height: `${viewport?.height ?? window.innerHeight}px`,
        transform: `translateY(${viewport?.offsetTop ?? 0}px)`,
    };
};

const listenToAmountEditorViewport = () => {
    if (window.visualViewport) {
        window.visualViewport.addEventListener('resize', updateAmountEditorViewport);
        window.visualViewport.addEventListener('scroll', updateAmountEditorViewport);
    }
};

const stopListeningToAmountEditorViewport = () => {
    if (window.visualViewport) {
        window.visualViewport.removeEventListener('resize', updateAmountEditorViewport);
        window.visualViewport.removeEventListener('scroll', updateAmountEditorViewport);
    }
};

const lockPageScroll = () => {
    previousBodyOverflow = document.body.style.overflow;
    previousDocumentOverflow = document.documentElement.style.overflow;
    document.body.style.overflow = 'hidden';
    document.documentElement.style.overflow = 'hidden';
};

const restorePageScroll = () => {
    document.body.style.overflow = previousBodyOverflow;
    document.documentElement.style.overflow = previousDocumentOverflow;
};

const openEditor = async () => {
    lockPageScroll();
    updateAmountEditorViewport();
    listenToAmountEditorViewport();
    amountDraft.value = props.amount === '' ? '' : String(props.amount).replace('.', ',');
    expressionTokens.value = [];

    await nextTick();
    amountEditorInputRef.value?.focus();
    amountEditorInputRef.value?.select();
};

const closeEditor = () => {
    if (hasCalculationInput.value && totalValue.value === null) {
        return;
    }

    emit('update:amount', totalValue.value === null ? '' : String(totalValue.value));
    stopListeningToAmountEditorViewport();
    restorePageScroll();
    emit('close');
};

const updateAmountDraft = (value) => {
    amountDraft.value = value;
    emit('update:amount', normalizeAmount(value));
};

const appendOperator = async (operator) => {
    if (amountDraft.value.trim() !== '') {
        expressionTokens.value.push(normalizeAmount(amountDraft.value), operator);
        amountDraft.value = '';
        emit('update:amount', '');
    } else if (isOperator(expressionTokens.value.at(-1))) {
        expressionTokens.value[expressionTokens.value.length - 1] = operator;
    } else if (expressionTokens.value.length > 0) {
        expressionTokens.value.push(operator);
    }

    await nextTick();
    amountEditorInputRef.value?.focus({ preventScroll: true });
};

const removeLastToken = async () => {
    if (amountDraft.value !== '') {
        updateAmountDraft(amountDraft.value.slice(0, -1));
    } else if (expressionTokens.value.length > 0) {
        const lastToken = expressionTokens.value.pop();

        if (!isOperator(lastToken)) {
            emit('update:amount', '');
        }
    }

    await nextTick();
    amountEditorInputRef.value?.focus({ preventScroll: true });
};

watch(() => props.open, (isOpen) => {
    if (isOpen) {
        void openEditor();
    }
});

onBeforeUnmount(() => {
    stopListeningToAmountEditorViewport();

    if (props.open) {
        restorePageScroll();
    }
});
</script>

<template>
    <div v-if="open" class="fixed inset-x-0 top-0 z-50 flex flex-col overscroll-contain overflow-hidden bg-white px-5 py-6 dark:bg-slate-950 sm:hidden" :style="amountEditorStyle" role="dialog" aria-label="Editar monto" aria-modal="true">
        <span class="text-sm font-medium text-slate-500 dark:text-slate-400">Monto</span>
        <div class="flex min-h-0 flex-1 flex-col items-center justify-center gap-6">
            <div class="min-h-8 w-full text-center text-lg font-medium tabular-nums text-slate-500 dark:text-slate-400" aria-live="polite">{{ expressionDisplay }}</div>
            <div class="w-full text-center text-5xl font-semibold tabular-nums text-slate-900 dark:text-slate-100" aria-live="polite">{{ formattedAmountDraft }}</div>
            <input ref="amountEditorInputRef" :value="amountDraft" type="text" inputmode="decimal" autocomplete="off" class="w-full rounded-md border border-slate-300 bg-white px-4 py-3 text-center text-2xl tabular-nums text-slate-900 outline-none focus:border-slate-900 focus:ring-2 focus:ring-slate-400 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100 dark:focus:border-slate-100" aria-label="Monto" @input="updateAmountDraft($event.target.value)" @keydown.esc.prevent="closeEditor" @keydown.enter.prevent="closeEditor">
            <div class="grid w-full grid-cols-5 gap-2">
                <button v-for="operator in operatorButtons" :key="operator.value" type="button" class="rounded-md border border-slate-300 bg-slate-100 px-3 py-3 text-xl font-semibold text-slate-900 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100" :aria-label="`Operación ${operator.label}`" @click="appendOperator(operator.value)">{{ operator.label }}</button>
                <button type="button" class="rounded-md border border-slate-300 bg-slate-100 px-3 py-3 text-xl font-semibold text-slate-900 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100" aria-label="Eliminar último" @click="removeLastToken">⌫</button>
            </div>
            <div class="flex w-full items-center justify-between border-t border-slate-200 pt-4 text-lg dark:border-slate-800">
                <span class="text-slate-500 dark:text-slate-400">Total</span>
                <span class="font-semibold tabular-nums text-slate-900 dark:text-slate-100">{{ formattedTotal }}</span>
            </div>
        </div>
        <button type="button" class="mt-auto w-full rounded-md bg-slate-900 px-4 py-3 text-sm font-medium text-white dark:bg-slate-100 dark:text-slate-900" @click="closeEditor">Listo</button>
    </div>
</template>

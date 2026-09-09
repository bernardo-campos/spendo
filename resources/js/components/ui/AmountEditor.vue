<script setup>
import { nextTick, onBeforeUnmount, ref, watch } from 'vue';

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

const formattedAmountDraft = () => {
    const normalizedAmount = normalizeAmount(amountDraft.value);

    return normalizedAmount === '' ? '0,00' : props.formatAmount(Number(normalizedAmount));
};

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

    await nextTick();
    amountEditorInputRef.value?.focus();
    amountEditorInputRef.value?.select();
};

const closeEditor = () => {
    emit('update:amount', normalizeAmount(amountDraft.value));
    stopListeningToAmountEditorViewport();
    restorePageScroll();
    emit('close');
};

const updateAmountDraft = (value) => {
    amountDraft.value = value;
    emit('update:amount', normalizeAmount(value));
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
            <div class="w-full text-center text-5xl font-semibold tabular-nums text-slate-900 dark:text-slate-100" aria-live="polite">{{ formattedAmountDraft() }}</div>
            <input ref="amountEditorInputRef" :value="amountDraft" type="text" inputmode="decimal" autocomplete="off" class="w-full rounded-md border border-slate-300 bg-white px-4 py-3 text-center text-2xl tabular-nums text-slate-900 outline-none focus:border-slate-900 focus:ring-2 focus:ring-slate-400 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100 dark:focus:border-slate-100" aria-label="Monto" @input="updateAmountDraft($event.target.value)" @keydown.esc.prevent="closeEditor" @keydown.enter.prevent="closeEditor">
        </div>
        <button type="button" class="mt-auto w-full rounded-md bg-slate-900 px-4 py-3 text-sm font-medium text-white dark:bg-slate-100 dark:text-slate-900" @click="closeEditor">Listo</button>
    </div>
</template>

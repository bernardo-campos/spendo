<script setup>
import { ref, watch } from 'vue';
import { ClipboardCopy, MailPlus, RefreshCw } from '@lucide/vue';

const props = defineProps({
    address: { type: String, default: null },
    loading: { type: Boolean, required: true },
    saving: { type: Boolean, required: true },
});

const emit = defineEmits(['generate', 'rotate']);
const addressInput = ref(null);
const copyFeedback = ref('');

watch(() => props.address, () => {
    copyFeedback.value = '';
});

const copyAddress = async () => {
    if (!props.address) {
        return;
    }

    try {
        await navigator.clipboard.writeText(props.address);
        copyFeedback.value = 'Dirección copiada.';
    } catch {
        addressInput.value?.focus();
        addressInput.value?.select();
        copyFeedback.value = 'Seleccioná y copiá la dirección.';
    }
};
</script>

<template>
    <section class="grid gap-6">
        <article class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
            <h2 class="text-base font-semibold">Importar recibos por correo</h2>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Reenviá tus recibos de Uber a tu dirección personal de Spendo. No necesitás dar acceso a tu correo.</p>

            <p v-if="loading" class="mt-5 text-sm text-slate-500 dark:text-slate-400">Cargando dirección...</p>

            <div v-else-if="address" class="mt-5 grid gap-3">
                <label for="receipt-import-address" class="text-sm font-medium">Tu dirección de importación</label>
                <div class="flex flex-col gap-2 sm:flex-row">
                    <input id="receipt-import-address" ref="addressInput" :value="address" type="text" readonly autocomplete="off" spellcheck="false" class="min-w-0 flex-1 rounded-md border border-slate-300 bg-slate-50 px-3 py-2 font-mono text-sm text-slate-900 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100" @focus="$event.target.select()">
                    <button type="button" class="inline-flex items-center justify-center gap-2 rounded-md bg-slate-900 px-3 py-2 text-sm font-medium text-white hover:bg-slate-800 dark:bg-slate-100 dark:text-slate-900 dark:hover:bg-slate-200" @click="copyAddress">
                        <ClipboardCopy class="size-4" />
                        Copiar
                    </button>
                </div>
                <p role="status" class="text-sm text-slate-500 dark:text-slate-400">{{ copyFeedback }}</p>
                <p class="text-sm text-slate-500 dark:text-slate-400">Guardá esta dirección como privada: quien la conozca podría enviarte recibos.</p>
                <button type="button" :disabled="saving" class="inline-flex items-center gap-2 justify-self-start rounded-md border border-slate-300 px-3 py-2 text-sm font-medium hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-60 dark:border-slate-700 dark:hover:bg-slate-800" @click="emit('rotate')">
                    <RefreshCw class="size-4" />
                    {{ saving ? 'Regenerando...' : 'Regenerar dirección' }}
                </button>
                <p class="text-xs text-slate-500 dark:text-slate-400">Al regenerarla, la dirección anterior deja de funcionar. Actualizá cualquier regla de reenvío que hayas creado.</p>
            </div>

            <div v-else class="mt-5 grid gap-3">
                <p class="text-sm text-slate-500 dark:text-slate-400">Todavía no tenés una dirección de importación.</p>
                <button type="button" :disabled="saving" class="inline-flex items-center gap-2 justify-self-start rounded-md bg-slate-900 px-3 py-2 text-sm font-medium text-white hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-60 dark:bg-slate-100 dark:text-slate-900 dark:hover:bg-slate-200" @click="emit('generate')">
                    <MailPlus class="size-4" />
                    {{ saving ? 'Generando...' : 'Generar dirección' }}
                </button>
            </div>
        </article>

        <article class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
            <h3 class="text-sm font-semibold">Cómo funciona</h3>
            <ol class="mt-3 list-inside list-decimal space-y-2 text-sm text-slate-600 dark:text-slate-300">
                <li>Copiá tu dirección de importación.</li>
                <li>Desde tu correo personal, reenviá el recibo de Uber a esa dirección.</li>
                <li>Si el recibo se reconoce, Spendo crea un egreso. Revisalo en Egresos.</li>
            </ol>
            <p class="mt-4 text-sm text-slate-500 dark:text-slate-400">La fecha del egreso será la del correo reenviado y la forma de pago será Efectivo. Podés editar el egreso si pagaste de otra manera.</p>
        </article>
    </section>
</template>

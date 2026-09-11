<script setup>
import { computed } from 'vue';

const props = defineProps({
    loading: { type: Boolean, required: true },
    preferences: { type: Object, required: true },
    saving: { type: Boolean, required: true },
});

const emit = defineEmits(['submit']);

const canHideCategory = computed(() => props.preferences.show_description);
const canHideDescription = computed(() => props.preferences.show_category);
</script>

<template>
    <section class="grid gap-6">
        <article class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
            <h2 class="text-base font-semibold">Visualización</h2>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Elegí qué información mostrar en el listado de gastos.</p>

            <p v-if="loading" class="mt-4 text-sm text-slate-500 dark:text-slate-400">Cargando preferencias...</p>

            <form v-else class="mt-4 grid gap-5" @submit.prevent="emit('submit')">
                <fieldset class="grid gap-3">
                    <legend class="text-sm font-medium">Información principal</legend>
                    <label class="flex items-center justify-between gap-4 rounded-md border border-slate-200 px-3 py-2 text-sm dark:border-slate-700">
                        <span>Categoría</span>
                        <input v-model="preferences.show_category" type="checkbox" :disabled="!canHideCategory">
                    </label>
                    <label class="flex items-center justify-between gap-4 rounded-md border border-slate-200 px-3 py-2 text-sm dark:border-slate-700">
                        <span>Descripción</span>
                        <input v-model="preferences.show_description" type="checkbox" :disabled="!canHideDescription">
                    </label>
                    <p class="text-xs text-slate-500 dark:text-slate-400">Debe quedar visible al menos la categoría o la descripción.</p>
                </fieldset>

                <fieldset class="grid gap-3">
                    <legend class="text-sm font-medium">Detalles adicionales</legend>
                    <label class="flex items-center justify-between gap-4 rounded-md border border-slate-200 px-3 py-2 text-sm dark:border-slate-700">
                        <span>Etiquetas</span>
                        <input v-model="preferences.show_tags" type="checkbox">
                    </label>
                    <label class="flex items-center justify-between gap-4 rounded-md border border-slate-200 px-3 py-2 text-sm dark:border-slate-700">
                        <span>Notas</span>
                        <input v-model="preferences.show_notes" type="checkbox">
                    </label>
                </fieldset>

                <fieldset class="grid gap-3">
                    <legend class="text-sm font-medium">Forma de pago</legend>
                    <label class="flex items-center justify-between gap-4 rounded-md border border-slate-200 px-3 py-2 text-sm dark:border-slate-700">
                        <span>Mostrar “Efectivo”</span>
                        <input v-model="preferences.show_cash_payment_method" type="checkbox">
                    </label>
                    <label class="flex items-center justify-between gap-4 rounded-md border border-slate-200 px-3 py-2 text-sm dark:border-slate-700">
                        <span>Mostrar “Crédito”</span>
                        <input v-model="preferences.show_credit_payment_method" type="checkbox">
                    </label>
                </fieldset>

                <button type="submit" :disabled="saving" class="justify-self-start rounded-md bg-slate-900 px-3 py-2 text-sm font-medium text-white hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-60 dark:bg-slate-100 dark:text-slate-900 dark:hover:bg-slate-200">
                    {{ saving ? 'Guardando...' : 'Guardar cambios' }}
                </button>
            </form>
        </article>
    </section>
</template>

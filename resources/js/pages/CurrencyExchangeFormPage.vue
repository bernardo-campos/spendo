<script setup>
import { computed } from 'vue';
import { ArrowLeft } from '@lucide/vue';

const props = defineProps({
    form: { type: Object, required: true },
    currencies: { type: Array, required: true },
    editing: { type: Boolean, required: true },
    saving: { type: Boolean, required: true },
    deleting: { type: Boolean, required: true },
    hasSuggestion: { type: Boolean, required: true },
});

const emit = defineEmits(['back', 'submit', 'delete', 'change-type', 'source-input', 'target-input']);
const rate = computed(() => {
    const source = Number(props.form.source_amount);
    const target = Number(props.form.target_amount);

    return source > 0 && target > 0 ? (source / target).toLocaleString('es-AR', { maximumFractionDigits: 4 }) : null;
});
</script>

<template>
    <section class="grid gap-4 rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
        <div class="flex items-center gap-2">
            <button type="button" class="rounded-md p-1 text-slate-600 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800" aria-label="Volver al listado" @click="emit('back')"><ArrowLeft class="size-4" /></button>
            <h2 class="font-semibold">{{ editing ? 'Editar cambio de moneda' : 'Registrar cambio de moneda' }}</h2>
        </div>
        <form class="grid gap-4" @submit.prevent="emit('submit')">
            <label v-if="!editing" class="grid gap-1 text-sm">
                <span>Tipo</span>
                <select class="rounded-md border border-slate-300 bg-white px-3 py-2 dark:border-slate-700 dark:bg-slate-950" value="exchange" @change="emit('change-type', $event.target.value)">
                    <option value="expense">Gasto</option><option value="income">Ingreso</option><option value="exchange">Cambio de moneda</option>
                </select>
            </label>
            <div class="grid gap-3 sm:grid-cols-2">
                <label class="grid gap-1 text-sm"><span>Gastás</span><input v-model="form.source_amount" type="number" min="0.01" step="0.01" required class="rounded-md border border-slate-300 bg-white px-3 py-2 dark:border-slate-700 dark:bg-slate-950" @input="emit('source-input')"></label>
                <label class="grid gap-1 text-sm"><span>Moneda entregada</span><select v-model="form.source_currency" class="rounded-md border border-slate-300 bg-white px-3 py-2 dark:border-slate-700 dark:bg-slate-950"><option v-for="currency in currencies" :key="currency.value" :value="currency.value">{{ currency.label }}</option></select></label>
                <label class="grid gap-1 text-sm"><span>Recibís</span><input v-model="form.target_amount" type="number" min="0.01" step="0.01" required class="rounded-md border border-slate-300 bg-white px-3 py-2 dark:border-slate-700 dark:bg-slate-950" @input="emit('target-input')"></label>
                <label class="grid gap-1 text-sm"><span>Moneda recibida</span><select v-model="form.target_currency" class="rounded-md border border-slate-300 bg-white px-3 py-2 dark:border-slate-700 dark:bg-slate-950"><option v-for="currency in currencies" :key="currency.value" :value="currency.value">{{ currency.label }}</option></select></label>
            </div>
            <p v-if="hasSuggestion" class="text-xs text-slate-500 dark:text-slate-400">Monto sugerido según tu último cambio entre estas monedas. Podés modificar ambos importes.</p>
            <p v-if="rate" class="text-sm text-slate-600 dark:text-slate-300">Cotización efectiva: 1 {{ form.target_currency }} = {{ rate }} {{ form.source_currency }}</p>
            <label class="grid gap-1 text-sm"><span>Descripción</span><input v-model="form.description" type="text" maxlength="255" required class="rounded-md border border-slate-300 bg-white px-3 py-2 dark:border-slate-700 dark:bg-slate-950"></label>
            <label class="grid gap-1 text-sm"><span>Lugar (opcional)</span><input v-model="form.place" type="text" maxlength="120" class="rounded-md border border-slate-300 bg-white px-3 py-2 dark:border-slate-700 dark:bg-slate-950"></label>
            <label class="grid gap-1 text-sm"><span>Fecha</span><input v-model="form.purchase_date" type="date" required class="rounded-md border border-slate-300 bg-white px-3 py-2 dark:border-slate-700 dark:bg-slate-950"></label>
            <label class="grid gap-1 text-sm"><span>Notas (opcional)</span><textarea v-model="form.notes" rows="3" maxlength="5000" class="rounded-md border border-slate-300 bg-white px-3 py-2 dark:border-slate-700 dark:bg-slate-950" /></label>
            <div class="flex justify-between gap-3">
                <button v-if="editing" type="button" :disabled="deleting" class="rounded-md border border-rose-300 px-4 py-2 text-sm text-rose-700 dark:border-rose-800 dark:text-rose-300" @click="emit('delete')">Eliminar cambio</button>
                <span v-else />
                <button type="submit" :disabled="saving || form.source_currency === form.target_currency" class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white disabled:opacity-50 dark:bg-slate-100 dark:text-slate-900">{{ saving ? 'Guardando...' : 'Guardar cambio' }}</button>
            </div>
        </form>
    </section>
</template>

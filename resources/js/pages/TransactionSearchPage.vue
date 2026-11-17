<script setup>
import { ChevronDown, Search } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import { normalizeSearchValue } from '@/utils/transactionSearch';

const props = defineProps({
    form: { type: Object, required: true },
    categories: { type: Array, required: true },
    tags: { type: Array, required: true },
    cards: { type: Array, required: true },
    results: { type: Object, default: null },
    appliedFilters: { type: Object, default: null },
    loading: { type: Boolean, required: true },
    error: { type: String, default: '' },
    formatCurrencyAmount: { type: Function, required: true },
});

const emit = defineEmits(['search', 'page']);
const filtersExpanded = ref(!props.results || Boolean(props.error));
const submitSearch = () => {
    filtersExpanded.value = false;
    emit('search');
};
watch(() => props.error, (error) => {
    if (error) {
        filtersExpanded.value = true;
    }
});
const tagQuery = ref('');
const categoryOptions = computed(() => props.categories.filter((category) => category.scope === 'both' || category.scope === props.form.type));
const filteredTags = computed(() => props.tags.filter((tag) => normalizeSearchValue(tag.name).includes(normalizeSearchValue(tagQuery.value))));
const inputClass = 'w-full min-w-0 rounded-md border border-input bg-background px-3 py-2 text-sm outline-none focus-visible:ring-2 focus-visible:ring-ring';
const resultSummary = computed(() => {
    if (!props.appliedFilters) {
        return '';
    }
    const filters = props.appliedFilters;
    const type = filters.type === 'income' ? 'Ingresos' : 'Egresos';
    const dates = filters.date_scope === 'all' ? 'todos los registros'
        : filters.date_scope === 'period' ? `período ${filters.period}`
            : `desde ${formatDate(filters.date_from)} hasta ${formatDate(filters.date_to)}`;

    return `${type} · ${dates}`;
});
const formatDate = (date) => {
    const [year, month, day] = String(date ?? '').slice(0, 10).split('-');
    return day ? `${day}/${month}/${year}` : '—';
};

watch(() => props.form.type, () => {
    if (!categoryOptions.value.some((category) => String(category.id) === String(props.form.category_id))) {
        props.form.category_id = '';
    }
    if (props.form.type === 'income') {
        props.form.payment_method = '';
        props.form.card_id = '';
    }
});
watch(() => props.form.payment_method, (method) => {
    if (method === 'cash') {
        props.form.card_id = '';
    }
});
</script>

<template>
    <section class="space-y-5 pt-4 sm:pt-0">
        <div>
            <h1 class="text-xl font-semibold">Búsqueda</h1>
            <p class="mt-1 text-sm text-muted-foreground">Buscá movimientos registrados por fecha de compra o ingreso. Los campos que completes se combinan; los vacíos no filtran.</p>
        </div>

        <div class="rounded-lg border border-border bg-card">
            <button type="button" class="flex w-full items-center justify-between gap-3 rounded-lg p-4 text-left text-sm font-semibold outline-none focus-visible:ring-2 focus-visible:ring-ring sm:p-5" :aria-expanded="filtersExpanded" aria-controls="transaction-search-filters" @click="filtersExpanded = !filtersExpanded">
                <span>Filtros de búsqueda</span>
                <ChevronDown class="size-4 shrink-0 transition-transform" :class="filtersExpanded ? 'rotate-180' : ''" />
            </button>
            <form v-show="filtersExpanded" id="transaction-search-filters" class="px-4 pb-4 sm:px-5 sm:pb-5" @submit.prevent="submitSearch">
                <fieldset :disabled="loading" class="grid gap-5 disabled:opacity-70">
                    <fieldset class="flex flex-wrap gap-x-6 gap-y-2">
                        <legend class="mb-2 text-sm font-semibold">Tipo de movimiento</legend>
                        <label class="flex items-center gap-2 text-sm"><input v-model="form.type" type="radio" name="search-type" value="income" class="size-4 accent-primary">Ingreso</label>
                        <label class="flex items-center gap-2 text-sm"><input v-model="form.type" type="radio" name="search-type" value="expense" class="size-4 accent-primary">Egreso</label>
                    </fieldset>

                    <fieldset class="grid gap-3 border-t border-border pt-4">
                        <legend class="text-sm font-semibold">Fechas</legend>
                        <div class="flex flex-wrap gap-x-5 gap-y-2">
                            <label v-for="scope in [{ value: 'period', label: 'Un período' }, { value: 'range', label: 'Intervalo de fechas' }, { value: 'all', label: 'Todos los registros' }]" :key="scope.value" class="flex items-center gap-2 text-sm">
                                <input v-model="form.date_scope" type="radio" name="search-date-scope" :value="scope.value" class="size-4 accent-primary">{{ scope.label }}
                            </label>
                        </div>
                        <label v-if="form.date_scope === 'period'" class="grid max-w-xs gap-1 text-sm font-medium">Período<input v-model="form.period" type="month" required :class="inputClass"></label>
                        <div v-if="form.date_scope === 'range'" class="grid gap-3 sm:grid-cols-2">
                            <label class="grid gap-1 text-sm font-medium">Desde<input v-model="form.date_from" type="date" required :max="form.date_to || undefined" :class="inputClass"></label>
                            <label class="grid gap-1 text-sm font-medium">Hasta<input v-model="form.date_to" type="date" required :min="form.date_from || undefined" :class="inputClass"></label>
                        </div>
                    </fieldset>

                    <div class="grid gap-4 border-t border-border pt-4 sm:grid-cols-2 lg:grid-cols-3">
                        <label v-for="field in [{ key: 'description', label: 'Descripción', max: 255 }, { key: 'place', label: 'Lugar', max: 120 }, { key: 'notes', label: 'Nota', max: 1000 }]" :key="field.key" class="grid gap-1 text-sm font-medium">
                            {{ field.label }}<input v-model="form[field.key]" type="text" :maxlength="field.max" placeholder="Contiene…" :class="inputClass">
                        </label>
                        <label class="grid gap-1 text-sm font-medium">Monto desde<input v-model="form.amount_min" type="number" min="0" step="0.01" placeholder="Sin mínimo" :class="inputClass"></label>
                        <label class="grid gap-1 text-sm font-medium">Monto hasta<input v-model="form.amount_max" type="number" :min="form.amount_min || 0" step="0.01" placeholder="Sin máximo" :class="inputClass"></label>
                        <label class="grid gap-1 text-sm font-medium">Moneda<select v-model="form.currency" :class="inputClass"><option value="">Todas</option><option value="ARS">Pesos (ARS)</option><option value="USD">Dólares (USD)</option></select></label>
                        <label class="grid gap-1 text-sm font-medium">Categoría
                            <select v-model="form.category_id" :class="inputClass"><option value="">Todas</option><option v-for="category in categoryOptions" :key="category.id" :value="category.id">{{ category.name }}</option></select>
                        </label>
                        <template v-if="form.type === 'expense'">
                            <label class="grid gap-1 text-sm font-medium">Medio de pago<select v-model="form.payment_method" :class="inputClass"><option value="">Todos</option><option value="cash">Efectivo</option><option value="credit">Crédito</option></select></label>
                            <label v-if="form.payment_method !== 'cash'" class="grid gap-1 text-sm font-medium">Tarjeta<select v-model="form.card_id" :class="inputClass"><option value="">Todas</option><option v-for="card in cards" :key="card.id" :value="card.id">{{ card.name }}</option></select></label>
                        </template>
                    </div>

                    <fieldset class="grid gap-2">
                        <legend class="mb-2 text-sm font-medium">Etiquetas (cualquiera de las seleccionadas)</legend>
                        <input v-model="tagQuery" type="search" aria-label="Filtrar listado de etiquetas" placeholder="Buscar una etiqueta…" :class="inputClass">
                        <div class="flex max-h-36 flex-wrap gap-2 overflow-y-auto rounded-md border border-border p-2">
                            <label v-for="tag in filteredTags" :key="tag.id" class="flex cursor-pointer items-center gap-2 rounded-md bg-accent px-3 py-2 text-sm">
                                <input v-model="form.tag_ids" type="checkbox" :value="tag.id" class="size-4 accent-primary">{{ tag.name }}
                            </label>
                            <p v-if="!filteredTags.length" class="p-1 text-sm text-muted-foreground">No hay etiquetas para mostrar.</p>
                        </div>
                        <p class="text-xs text-muted-foreground">{{ form.tag_ids.length ? `${form.tag_ids.length} seleccionadas` : 'Sin selección: busca con cualquier etiqueta o sin etiquetas.' }}</p>
                    </fieldset>

                    <button type="submit" class="inline-flex items-center justify-center gap-2 justify-self-end rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:bg-primary/90 disabled:opacity-60"><Search class="size-4" />{{ loading ? 'Buscando…' : 'Buscar' }}</button>
                </fieldset>
            </form>
        </div>

        <p v-if="error" role="alert" class="rounded-md border border-red-200 bg-red-50 p-3 text-sm text-red-700 dark:border-red-900 dark:bg-red-950 dark:text-red-200">{{ error }}</p>
        <p v-if="loading" role="status" class="text-sm text-muted-foreground">Buscando movimientos…</p>
        <section v-if="results && !loading" class="space-y-3" aria-label="Resultados de búsqueda">
            <div class="flex flex-wrap items-baseline justify-between gap-2">
                <h2 class="font-semibold">{{ results.meta.total }} resultados</h2>
                <p class="text-sm text-muted-foreground">{{ resultSummary }}</p>
            </div>
            <p v-if="!results.data.length" class="rounded-md border border-border p-5 text-sm text-muted-foreground">No se encontraron movimientos con los filtros indicados.</p>
            <ul v-else class="divide-y divide-border rounded-md border border-border bg-card">
                <li v-for="transaction in results.data" :key="transaction.id" class="space-y-2 p-4">
                    <div class="flex items-start justify-between gap-4">
                        <div class="min-w-0"><p class="break-words font-medium">{{ transaction.description }}</p><p class="text-sm text-muted-foreground">{{ formatDate(transaction.purchase_date) }} · {{ transaction.category?.name ?? 'Sin categoría' }}</p></div>
                        <span class="shrink-0 text-sm font-semibold tabular-nums">{{ formatCurrencyAmount(transaction.currency, transaction.amount) }}</span>
                    </div>
                    <p v-if="transaction.place" class="break-words text-sm text-muted-foreground">Lugar: {{ transaction.place }}</p>
                    <p v-if="transaction.notes" class="whitespace-pre-wrap break-words text-sm text-muted-foreground">Nota: {{ transaction.notes }}</p>
                    <p v-if="transaction.tags?.length" class="text-sm text-muted-foreground">Etiquetas: {{ transaction.tags.map((tag) => tag.name).join(' · ') }}</p>
                    <p v-if="transaction.type === 'expense'" class="text-xs text-muted-foreground">{{ transaction.payment_method === 'credit' ? `Crédito · ${transaction.card?.name ?? 'Sin tarjeta'}` : 'Efectivo' }}<template v-if="transaction.installment_plan"> · {{ transaction.installment_plan.installments_count }} cuotas · importe total de la compra</template></p>
                </li>
            </ul>
            <div v-if="results.meta.last_page > 1" class="flex items-center justify-between gap-3 text-sm">
                <button type="button" :disabled="results.meta.current_page === 1" class="rounded-md border border-border px-3 py-2 hover:bg-accent disabled:opacity-40" @click="emit('page', results.meta.current_page - 1)">Anterior</button>
                <span>Página {{ results.meta.current_page }} de {{ results.meta.last_page }}</span>
                <button type="button" :disabled="results.meta.current_page === results.meta.last_page" class="rounded-md border border-border px-3 py-2 hover:bg-accent disabled:opacity-40" @click="emit('page', results.meta.current_page + 1)">Siguiente</button>
            </div>
            <div class="flex flex-wrap items-center gap-x-3 gap-y-1 rounded-md border border-slate-200 bg-white px-3 py-2 text-sm shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <span class="font-semibold text-slate-900 dark:text-slate-100">Total <span class="font-normal text-slate-500 dark:text-slate-400">[filtrado]</span></span>
                <span class="flex flex-wrap gap-x-3 font-medium tabular-nums text-slate-700 dark:text-slate-200">
                    <span>{{ formatCurrencyAmount('ARS', results.totals.ARS) }}</span>
                    <span>{{ formatCurrencyAmount('USD', results.totals.USD) }}</span>
                </span>
            </div>
        </section>
    </section>
</template>

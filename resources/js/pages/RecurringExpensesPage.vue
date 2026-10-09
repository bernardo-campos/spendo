<script setup>
import { ArrowLeft, Check, ChevronsUpDown, MapPin } from '@lucide/vue';
import { onClickOutside } from '@vueuse/core';
import { computed, ref, watch } from 'vue';
import AmountEditor from '@/components/ui/AmountEditor.vue';
import {
    Combobox,
    ComboboxAnchor,
    ComboboxGroup,
    ComboboxItem,
    ComboboxItemIndicator,
    ComboboxList,
    ComboboxTrigger,
} from '@/components/ui/combobox';
import { formatLocalDate } from '../utils/localDate';
import { recurringExpenseSchedule } from '../utils/recurringExpenseSchedule';

const currencies = [
    { value: 'ARS', label: 'Pesos argentinos (AR$)', symbol: 'AR$' },
    { value: 'USD', label: 'Dólares (USD$)', symbol: 'USD$' },
];
const paymentMethods = [
    { value: 'cash', label: 'Efectivo' },
    { value: 'credit', label: 'Crédito' },
];

const props = defineProps({
    rules: { type: Array, required: true },
    cards: { type: Array, required: true },
    categories: { type: Array, required: true },
    selectedPeriod: { type: String, required: true },
    formatCurrencyAmount: { type: Function, required: true },
    formatAmount: { type: Function, required: true },
    places: { type: Array, required: true },
    busy: { type: Boolean, default: false },
    offline: { type: Boolean, default: false },
});
const emit = defineEmits(['save', 'back']);
const editingId = ref(null);
const showForm = ref(false);
const visiblePreviewLimit = ref(60);
const amountEditorOpen = ref(false);
const amountInputRef = ref(null);
const placeFieldRef = ref(null);
const placeInputFocused = ref(false);
const formError = ref('');
const emptyForm = () => ({
    description: '', place: '', category_id: '', payment_method: 'cash', card_id: '',
    currency: 'ARS', amount_type: 'fixed', amount: '', day_of_month: Number(formatLocalDate().slice(8, 10)),
    starts_on: `${props.selectedPeriod}-01`, ends_on: '', is_active: true,
    notes: '', number_occurrences_in_notes: false,
    effective_period: props.selectedPeriod,
});
const form = ref(emptyForm());
const previewRule = computed(() => ({
    ...form.value,
    starts_on: editingId.value && form.value.effective_period > String(form.value.starts_on).slice(0, 7)
        ? `${form.value.effective_period}-01`
        : form.value.starts_on,
}));
const schedulePreview = computed(() => recurringExpenseSchedule(previewRule.value, visiblePreviewLimit.value));
const formatPreviewDate = (value) => String(value).split('-').reverse().join('/');
const expenseCategories = computed(() => props.categories.filter((category) => category.scope === 'expense' || category.scope === 'both'));
const selectedCategory = computed({
    get: () => expenseCategories.value.find((category) => Number(category.id) === Number(form.value.category_id)) ?? null,
    set: (category) => { form.value.category_id = category?.id ?? ''; },
});
const selectedPaymentMethod = computed({
    get: () => paymentMethods.find((method) => method.value === form.value.payment_method) ?? null,
    set: (method) => { form.value.payment_method = method?.value ?? 'cash'; },
});
const selectedCard = computed({
    get: () => props.cards.find((card) => Number(card.id) === Number(form.value.card_id)) ?? null,
    set: (card) => { form.value.card_id = card?.id ?? ''; },
});
const selectedCurrency = computed({
    get: () => currencies.find((currency) => currency.value === form.value.currency) ?? currencies[0],
    set: (currency) => { form.value.currency = currency?.value ?? 'ARS'; },
});
const filteredPlaces = computed(() => {
    const search = String(form.value.place ?? '').trim().toLocaleLowerCase('es-AR');

    return [...new Set(props.places.map((place) => String(place ?? '').trim()).filter(Boolean))]
        .filter((place) => place.toLocaleLowerCase('es-AR').includes(search));
});
const isPlaceDropdownOpen = computed(() => placeInputFocused.value
    && (filteredPlaces.value.length > 0 || String(form.value.place ?? '').trim()));
const placeMatchesSearch = computed(() => filteredPlaces.value
    .some((place) => place.toLocaleLowerCase('es-AR') === String(form.value.place).trim().toLocaleLowerCase('es-AR')));

onClickOutside(placeFieldRef, () => { placeInputFocused.value = false; });

const selectPlace = (place) => {
    form.value.place = place;
    placeInputFocused.value = false;
};
const useTypedPlace = () => selectPlace(String(form.value.place).trim());
const openAmountEditor = () => {
    if (!amountEditorOpen.value && window.matchMedia('(max-width: 639px)').matches) {
        amountEditorOpen.value = true;
    }
};
const closeAmountEditor = () => {
    amountEditorOpen.value = false;
    amountInputRef.value?.blur();
};

const edit = (rule) => {
    showForm.value = true;
    formError.value = '';
    editingId.value = rule.id;
    form.value = {
        description: rule.description,
        place: rule.place ?? '',
        category_id: rule.category_id ?? '',
        payment_method: rule.payment_method,
        card_id: rule.card_id ?? '',
        currency: rule.currency,
        amount_type: rule.amount_type,
        amount: rule.amount ?? '',
        day_of_month: rule.day_of_month,
        starts_on: String(rule.starts_on).slice(0, 10),
        ends_on: rule.ends_on ? String(rule.ends_on).slice(0, 10) : '',
        is_active: rule.is_active,
        effective_period: props.selectedPeriod,
        notes: rule.notes ?? '',
        number_occurrences_in_notes: Boolean(rule.number_occurrences_in_notes),
    };
};
const reset = () => {
    showForm.value = false;
    visiblePreviewLimit.value = 60;
    formError.value = '';
    editingId.value = null;
    form.value = emptyForm();
};
const submit = () => {
    formError.value = '';
    if (form.value.payment_method === 'credit' && !selectedCard.value) {
        formError.value = 'Seleccioná una tarjeta para este gasto recurrente.';
        return;
    }
    emit('save', {
        id: editingId.value,
        payload: {
            ...form.value,
            category_id: form.value.category_id || null,
            card_id: form.value.payment_method === 'credit' ? Number(form.value.card_id) || null : null,
            amount: form.value.amount_type === 'fixed' ? form.value.amount : null,
            place: form.value.place.trim() || null,
            ends_on: form.value.ends_on || null,
            notes: form.value.notes.trim() || null,
            number_occurrences_in_notes: Boolean(form.value.ends_on && form.value.number_occurrences_in_notes),
        },
    });
};
watch(() => props.selectedPeriod, (period) => {
    form.value.effective_period = period;
});
watch(() => form.value.ends_on, (endDate) => {
    if (!endDate) {
        form.value.number_occurrences_in_notes = false;
    }
});
watch(() => [form.value.starts_on, form.value.ends_on, form.value.day_of_month, form.value.effective_period], () => {
    visiblePreviewLimit.value = 60;
});
defineExpose({ reset });
</script>

<template>
    <section class="grid gap-6">
        <article v-if="showForm" class="rounded-none border-0 bg-transparent p-0 sm:rounded-lg sm:border sm:border-slate-200 sm:bg-white sm:p-4 dark:sm:border-slate-800 dark:sm:bg-slate-900">
            <div class="mb-4 flex items-center gap-2">
                <button type="button" class="rounded-md p-1 text-slate-600 hover:bg-slate-100 hover:text-slate-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-slate-400 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-slate-100" aria-label="Volver a gastos recurrentes" title="Volver a gastos recurrentes" @click="reset">
                    <ArrowLeft class="size-4" />
                </button>
                <h2 class="text-base font-semibold">{{ editingId ? 'Editar gasto recurrente' : 'Crear gasto recurrente' }}</h2>
            </div>

            <p v-if="offline" class="rounded-md border border-amber-300 bg-amber-50 p-3 text-sm text-amber-900 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-100">Conectate para consultar o modificar las recurrencias.</p>
            <form v-else class="space-y-3" @submit.prevent="submit">
                <div class="space-y-1 text-sm">
                    <span class="font-medium">Tipo de importe</span>
                    <select v-model="form.amount_type" class="w-full rounded-md border border-slate-300 bg-white px-3 py-2 dark:border-slate-700 dark:bg-slate-950">
                        <option value="fixed">Fijo todos los meses</option>
                        <option value="variable">Variable, confirmar cada mes</option>
                    </select>
                </div>

                <div class="space-y-1 text-sm">
                    <span class="font-medium">{{ form.amount_type === 'fixed' ? 'Monto mensual' : 'Moneda' }}</span>
                    <div class="flex w-full rounded-md">
                        <input v-if="form.amount_type === 'fixed'" ref="amountInputRef" v-model="form.amount" type="number" min="0.01" step="0.01" required class="min-w-0 flex-1 rounded-l-md border border-r-0 border-slate-300 bg-white px-3 py-2 outline-none focus-visible:ring-2 focus-visible:ring-slate-400 dark:border-slate-700 dark:bg-slate-950 dark:focus-visible:ring-slate-500" @focus="openAmountEditor">
                        <div v-else class="min-w-0 flex-1 truncate rounded-l-md border border-r-0 border-slate-300 bg-slate-50 px-3 py-2 text-slate-500 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-400">Se ingresa al confirmar el cargo</div>
                        <Combobox v-model="selectedCurrency" by="value">
                            <ComboboxAnchor as-child class="w-14">
                                <ComboboxTrigger as-child>
                                    <button type="button" class="flex w-14 shrink-0 items-center justify-center gap-0.5 rounded-r-md border border-slate-300 bg-white px-1.5 py-2 font-medium hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-slate-400 dark:border-slate-700 dark:bg-slate-950 dark:hover:bg-slate-900" aria-label="Seleccionar moneda">
                                        {{ selectedCurrency?.symbol ?? 'AR$' }}
                                        <ChevronsUpDown class="size-3 text-slate-500 dark:text-slate-400" />
                                    </button>
                                </ComboboxTrigger>
                            </ComboboxAnchor>
                            <ComboboxList class="w-60 border-slate-200 bg-white text-slate-900 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100" align="end">
                                <ComboboxGroup>
                                    <ComboboxItem v-for="currency in currencies" :key="currency.value" :value="currency">
                                        {{ currency.label }}
                                        <ComboboxItemIndicator class="ml-auto"><Check class="size-4" /></ComboboxItemIndicator>
                                    </ComboboxItem>
                                </ComboboxGroup>
                            </ComboboxList>
                        </Combobox>
                    </div>
                    <p v-if="form.amount_type === 'variable'" class="text-xs text-slate-500 dark:text-slate-400">El último monto confirmado se mostrará como sugerencia, pero no se registrará automáticamente.</p>
                </div>

                <label class="block space-y-1 text-sm">
                    <span class="font-medium">Descripción</span>
                    <input v-model="form.description" type="text" required maxlength="255" class="w-full rounded-md border border-slate-300 bg-white px-3 py-2 dark:border-slate-700 dark:bg-slate-950">
                </label>

                <label class="block space-y-1 text-sm">
                    <span class="font-medium">Nota <span class="font-normal text-slate-500 dark:text-slate-400">(opcional)</span></span>
                    <textarea v-model="form.notes" rows="2" maxlength="4800" class="w-full rounded-md border border-slate-300 bg-white px-3 py-2 dark:border-slate-700 dark:bg-slate-950"></textarea>
                </label>

                <div class="space-y-1 text-sm">
                    <span class="font-medium">Lugar <span class="font-normal text-slate-500 dark:text-slate-400">(opcional)</span></span>
                    <div ref="placeFieldRef" class="relative">
                        <div class="flex w-full items-center gap-2 rounded-md border border-slate-300 bg-white px-3 py-2 dark:border-slate-700 dark:bg-slate-950">
                            <MapPin class="size-4 shrink-0 text-slate-500 dark:text-slate-400" />
                            <input v-model="form.place" type="text" maxlength="120" placeholder="Buscar o escribir un lugar..." class="min-w-0 flex-1 bg-transparent outline-none" @focus="placeInputFocused = true" @keydown.enter.prevent="filteredPlaces[0] ? selectPlace(filteredPlaces[0]) : useTypedPlace()">
                            <button v-if="form.place || isPlaceDropdownOpen" type="button" class="text-xs text-slate-500 hover:text-slate-900 dark:text-slate-400 dark:hover:text-slate-100" @click="isPlaceDropdownOpen ? placeInputFocused = false : form.place = ''">{{ isPlaceDropdownOpen ? 'Cerrar' : 'Limpiar' }}</button>
                        </div>
                        <div v-if="isPlaceDropdownOpen" class="absolute z-10 mt-1 max-h-48 w-full overflow-y-auto rounded-md border border-slate-200 bg-white p-1 shadow-md dark:border-slate-700 dark:bg-slate-900">
                            <button v-for="place in filteredPlaces" :key="place" type="button" class="block w-full rounded-sm px-2 py-1.5 text-left text-sm hover:bg-slate-100 dark:hover:bg-slate-800" @mousedown.prevent="selectPlace(place)">{{ place }}</button>
                            <button v-if="String(form.place).trim() && !placeMatchesSearch" type="button" class="block w-full rounded-sm px-2 py-1.5 text-left text-sm font-medium hover:bg-slate-100 dark:hover:bg-slate-800" @mousedown.prevent="useTypedPlace">Usar “{{ String(form.place).trim() }}”</button>
                        </div>
                    </div>
                    <span class="text-xs text-slate-500 dark:text-slate-400">Elegí un lugar usado anteriormente o escribí uno nuevo.</span>
                </div>

                <div class="space-y-1 text-sm">
                    <span class="font-medium">Categoría</span>
                    <div class="flex flex-wrap gap-2" role="group" aria-label="Seleccionar categoría">
                        <button type="button" class="rounded-full px-3 py-1.5 text-sm font-medium transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-slate-400" :class="selectedCategory === null ? 'bg-slate-900 text-white dark:bg-slate-100 dark:text-slate-900' : 'bg-slate-100 text-slate-700 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700'" :aria-pressed="selectedCategory === null" @click="selectedCategory = null">Sin categoría</button>
                        <button v-for="category in expenseCategories" :key="category.id" type="button" class="rounded-full px-3 py-1.5 text-sm font-medium transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-slate-400" :class="Number(selectedCategory?.id) === Number(category.id) ? 'bg-slate-900 text-white dark:bg-slate-100 dark:text-slate-900' : 'bg-slate-100 text-slate-700 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700'" :aria-pressed="Number(selectedCategory?.id) === Number(category.id)" @click="selectedCategory = category">{{ category.name }}</button>
                        <span v-if="expenseCategories.length === 0" class="text-sm text-slate-500 dark:text-slate-400">No hay categorías disponibles.</span>
                    </div>
                </div>

                <div class="space-y-1 text-sm">
                    <span class="font-medium">Forma de pago</span>
                    <Combobox v-model="selectedPaymentMethod" by="value">
                        <ComboboxAnchor as-child>
                            <ComboboxTrigger as-child>
                                <button type="button" class="flex w-full items-center justify-between rounded-md border border-slate-300 bg-white px-3 py-2 text-left dark:border-slate-700 dark:bg-slate-950" aria-label="Seleccionar forma de pago">
                                    <span>{{ selectedPaymentMethod?.label ?? 'Selecciona una forma de pago' }}</span>
                                    <ChevronsUpDown class="ml-2 size-4 shrink-0 text-slate-500 dark:text-slate-400" />
                                </button>
                            </ComboboxTrigger>
                        </ComboboxAnchor>
                        <ComboboxList class="w-[var(--reka-combobox-trigger-width)] max-w-[calc(100vw-2rem)] border-slate-200 bg-white text-slate-900 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100" align="start">
                            <ComboboxGroup>
                                <ComboboxItem v-for="method in paymentMethods" :key="method.value" :value="method">{{ method.label }}<ComboboxItemIndicator class="ml-auto"><Check class="size-4" /></ComboboxItemIndicator></ComboboxItem>
                            </ComboboxGroup>
                        </ComboboxList>
                    </Combobox>
                </div>

                <div v-if="form.payment_method === 'credit'" class="space-y-1 text-sm">
                    <span class="font-medium">Tarjeta</span>
                    <Combobox v-model="selectedCard" by="id">
                        <ComboboxAnchor as-child>
                            <ComboboxTrigger as-child>
                                <button type="button" class="flex w-full items-center justify-between rounded-md border border-slate-300 bg-white px-3 py-2 text-left dark:border-slate-700 dark:bg-slate-950" aria-label="Seleccionar tarjeta">
                                    <span class="truncate">{{ selectedCard ? `${selectedCard.name} · ****${selectedCard.last_four_digits}` : 'Selecciona una tarjeta' }}</span>
                                    <ChevronsUpDown class="ml-2 size-4 shrink-0 text-slate-500 dark:text-slate-400" />
                                </button>
                            </ComboboxTrigger>
                        </ComboboxAnchor>
                        <ComboboxList class="w-[var(--reka-combobox-trigger-width)] max-w-[calc(100vw-2rem)] border-slate-200 bg-white text-slate-900 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100" align="start">
                            <ComboboxGroup>
                                <ComboboxItem v-for="card in cards" :key="card.id" :value="card">{{ card.name }} · ****{{ card.last_four_digits }}<ComboboxItemIndicator class="ml-auto"><Check class="size-4" /></ComboboxItemIndicator></ComboboxItem>
                            </ComboboxGroup>
                        </ComboboxList>
                    </Combobox>
                    <p class="text-xs text-slate-500 dark:text-slate-400">La fecha de pago se calculará según el cierre y vencimiento de la tarjeta.</p>
                </div>

                <div class="grid gap-3 border-t border-slate-200 pt-4 dark:border-slate-800 sm:grid-cols-2">
                    <label class="block space-y-1 text-sm">
                        <span class="font-medium">Día del cargo</span>
                        <input v-model.number="form.day_of_month" required type="number" min="1" max="31" class="w-full rounded-md border border-slate-300 bg-white px-3 py-2 dark:border-slate-700 dark:bg-slate-950">
                    </label>
                    <label class="block space-y-1 text-sm">
                        <span class="font-medium">Comienza</span>
                        <input v-model="form.starts_on" required type="date" class="w-full rounded-md border border-slate-300 bg-white px-3 py-2 dark:border-slate-700 dark:bg-slate-950">
                    </label>
                    <label class="block space-y-1 text-sm">
                        <span class="font-medium">Termina <span class="font-normal text-slate-500 dark:text-slate-400">(opcional)</span></span>
                        <input v-model="form.ends_on" type="date" :min="form.starts_on" class="w-full rounded-md border border-slate-300 bg-white px-3 py-2 dark:border-slate-700 dark:bg-slate-950">
                    </label>
                    <label v-if="editingId" class="block space-y-1 text-sm">
                        <span class="font-medium">Aplicar cambios desde</span>
                        <input v-model="form.effective_period" required type="month" class="w-full rounded-md border border-slate-300 bg-white px-3 py-2 dark:border-slate-700 dark:bg-slate-950">
                    </label>
                </div>
                <p class="text-xs text-slate-500 dark:text-slate-400">Si el mes no tiene ese día, se usará el último día disponible.</p>
                <div v-if="schedulePreview" class="space-y-3 rounded-md border border-slate-200 bg-slate-50 p-3 dark:border-slate-700 dark:bg-slate-950">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <h3 class="text-sm font-semibold">Vista previa de cargos</h3>
                        <span class="text-xs text-slate-500 dark:text-slate-400">{{ schedulePreview.total }} {{ schedulePreview.total === 1 ? 'repetición' : 'repeticiones' }}</span>
                    </div>
                    <label class="flex items-start gap-2 text-sm" :class="schedulePreview.total === 0 ? 'opacity-50' : ''">
                        <input v-model="form.number_occurrences_in_notes" type="checkbox" :disabled="schedulePreview.total === 0" class="mt-0.5 size-4 rounded border-slate-300 dark:border-slate-700">
                        <span>Agregar la numeración a la nota de cada cargo<span v-if="!editingId"> (por ejemplo, 1 de {{ schedulePreview.total }})</span></span>
                    </label>
                    <p v-if="editingId && form.number_occurrences_in_notes" class="text-xs text-slate-500 dark:text-slate-400">Al editar una regla, la numeración final conserva los cargos anteriores de la misma serie.</p>
                    <p v-if="schedulePreview.total === 0" class="text-sm text-slate-500 dark:text-slate-400">No hay fechas de cargo dentro del rango elegido.</p>
                    <ul v-else class="max-h-64 space-y-2 overflow-y-auto pr-1" aria-label="Cargos previstos">
                        <li v-for="item in schedulePreview.items" :key="item.date" class="flex flex-wrap items-start justify-between gap-x-3 border-b border-slate-200 pb-2 text-sm last:border-b-0 last:pb-0 dark:border-slate-800">
                            <div>
                                <p class="font-medium">{{ formatPreviewDate(item.date) }}</p>
                                <p v-if="!editingId && item.notes" class="whitespace-pre-line text-xs text-slate-500 dark:text-slate-400">Nota: {{ item.notes }}</p>
                            </div>
                            <span class="tabular-nums text-slate-600 dark:text-slate-300">{{ form.amount_type === 'fixed' && form.amount ? formatCurrencyAmount(form.currency, form.amount) : `≈ ${selectedCurrency?.symbol ?? form.currency} —` }}</span>
                        </li>
                    </ul>
                    <button v-if="schedulePreview.hiddenCount > 0" type="button" class="text-left text-xs font-medium text-slate-700 underline underline-offset-2 hover:text-slate-900 dark:text-slate-300 dark:hover:text-white" @click="visiblePreviewLimit += 60">Mostrar 60 cargos más ({{ schedulePreview.hiddenCount }} restantes)</button>
                </div>
                <label class="flex items-center gap-2 text-sm"><input v-model="form.is_active" type="checkbox" class="size-4 rounded border-slate-300 dark:border-slate-700"> Recurrencia activa</label>
                <p v-if="formError" class="text-sm text-red-700 dark:text-red-400" role="alert">{{ formError }}</p>
                <div class="grid gap-3 sm:grid-cols-2">
                    <button type="submit" class="w-full rounded-md bg-slate-900 px-3 py-2 text-sm font-medium text-white hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-60 dark:bg-slate-100 dark:text-slate-900 dark:hover:bg-slate-200" :disabled="busy">{{ busy ? 'Guardando...' : (editingId ? 'Guardar cambios' : 'Crear recurrencia') }}</button>
                    <button type="button" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm font-medium hover:bg-slate-50 dark:border-slate-700 dark:hover:bg-slate-800" @click="reset">Cancelar</button>
                </div>
            </form>
            <AmountEditor v-model:amount="form.amount" :format-amount="formatAmount" :open="amountEditorOpen" @close="closeAmountEditor" />
        </article>

        <div v-if="!showForm" class="space-y-3">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-2">
                    <button type="button" class="rounded-md p-1 text-slate-600 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800" aria-label="Volver a egresos" @click="emit('back')"><ArrowLeft class="size-4" /></button>
                    <h2 class="text-base font-semibold">Gastos recurrentes</h2>
                </div>
                <button type="button" class="rounded-md bg-slate-900 px-3 py-2 text-sm font-medium text-white hover:bg-slate-800 disabled:opacity-50 dark:bg-slate-100 dark:text-slate-900" :disabled="offline" @click="reset(); showForm = true">Agregar gasto recurrente</button>
            </div>
            <p v-if="offline" class="rounded-md border border-amber-300 bg-amber-50 p-3 text-sm text-amber-900">Conectate para consultar o modificar las recurrencias.</p>
            <p v-if="rules.length === 0" class="text-sm text-slate-500 dark:text-slate-400">Todavía no hay gastos recurrentes.</p>
            <div v-for="rule in rules" :key="rule.id" class="flex flex-wrap items-center gap-3 rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                <div class="min-w-40 flex-1">
                    <p class="font-medium">{{ rule.description }} <span v-if="!rule.is_active" class="text-xs text-slate-500 dark:text-slate-400">(pausada)</span></p>
                    <p class="text-xs text-slate-500 dark:text-slate-400">Día {{ rule.day_of_month }} · {{ rule.card?.name ?? (rule.payment_method === 'credit' ? 'Tarjeta no disponible' : 'Efectivo') }} · {{ rule.amount_type === 'fixed' ? formatCurrencyAmount(rule.currency, rule.amount) : 'Importe variable' }} · Desde {{ String(rule.starts_on).slice(0, 10) }}<template v-if="rule.ends_on"> hasta {{ String(rule.ends_on).slice(0, 10) }}</template></p>
                </div>
                <button type="button" class="rounded-md border border-slate-300 px-3 py-1.5 text-sm hover:bg-slate-50 dark:border-slate-700 dark:hover:bg-slate-800" @click="edit(rule)">Editar</button>
            </div>
        </div>
    </section>
</template>

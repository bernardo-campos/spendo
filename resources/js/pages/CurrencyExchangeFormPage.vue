<script setup>
import { computed, ref } from 'vue';
import { ArrowLeft, Check, ChevronsUpDown, X } from '@lucide/vue';
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

const props = defineProps({
    form: { type: Object, required: true },
    currencies: { type: Array, required: true },
    categories: { type: Array, required: true },
    tags: { type: Array, required: true },
    formatAmount: { type: Function, required: true },
    editing: { type: Boolean, required: true },
    saving: { type: Boolean, required: true },
    deleting: { type: Boolean, required: true },
    hasSuggestion: { type: Boolean, required: true },
});

const emit = defineEmits(['back', 'submit', 'delete', 'change-type', 'source-input', 'target-input']);
const tagSearch = ref('');
const tagInputFocused = ref(false);
const amountEditorField = ref(null);

const sourceCurrency = computed({
    get: () => props.currencies.find((currency) => currency.value === props.form.source_currency) ?? props.currencies[0] ?? null,
    set: (currency) => { props.form.source_currency = currency?.value ?? 'ARS'; },
});
const targetCurrency = computed({
    get: () => props.currencies.find((currency) => currency.value === props.form.target_currency) ?? props.currencies[1] ?? null,
    set: (currency) => { props.form.target_currency = currency?.value ?? 'USD'; },
});
const expenseCategoryOptions = computed(() => props.categories.filter((category) => ['expense', 'both'].includes(category.scope)));
const incomeCategoryOptions = computed(() => props.categories.filter((category) => ['income', 'both'].includes(category.scope)));
const selectedExpenseCategory = computed({
    get: () => expenseCategoryOptions.value.find((category) => Number(category.id) === Number(props.form.category_id)) ?? null,
    set: (category) => { props.form.category_id = category?.id ?? ''; },
});
const selectedIncomeCategory = computed({
    get: () => incomeCategoryOptions.value.find((category) => Number(category.id) === Number(props.form.income_category_id)) ?? null,
    set: (category) => { props.form.income_category_id = category?.id ?? ''; },
});
const selectedTagValues = computed({
    get: () => (props.form.tag_ids ?? []).map((tagId) => String(tagId)),
    set: (values) => {
        props.form.tag_ids = values.map((value) => Number(value))
            .filter((tagId) => props.tags.some((tag) => Number(tag.id) === tagId));
    },
});
const filteredTags = computed(() => {
    const searchTerm = tagSearch.value.trim().toLocaleLowerCase('es-AR');
    return props.tags.filter((tag) => !selectedTagValues.value.includes(String(tag.id))
        && tag.name.toLocaleLowerCase('es-AR').includes(searchTerm));
});
const isTagDropdownOpen = computed(() => tagInputFocused.value && filteredTags.value.length > 0);
const selectedTagName = (tagId) => props.tags.find((tag) => String(tag.id) === String(tagId))?.name ?? '';
const selectTag = (tag) => {
    selectedTagValues.value = [...selectedTagValues.value, String(tag.id)];
    tagSearch.value = '';
};
const selectFirstFilteredTag = () => {
    if (filteredTags.value[0]) {
        selectTag(filteredTags.value[0]);
    }
};
const rate = computed(() => {
    const source = Number(props.form.source_amount);
    const target = Number(props.form.target_amount);
    return source > 0 && target > 0 ? (source / target).toLocaleString('es-AR', { maximumFractionDigits: 4 }) : null;
});
const openAmountEditor = (field) => {
    if (window.matchMedia('(max-width: 639px)').matches) {
        amountEditorField.value = field;
    }
};
const updateEditedAmount = (amount) => {
    const field = amountEditorField.value;
    if (!field) {
        return;
    }
    props.form[field === 'source' ? 'source_amount' : 'target_amount'] = amount;
    emit(field === 'source' ? 'source-input' : 'target-input');
};
</script>

<template>
    <section class="grid gap-6">
        <article class="rounded-none border-0 bg-transparent p-0 sm:rounded-lg sm:border sm:border-slate-200 sm:bg-white sm:p-4 dark:sm:border-slate-800 dark:sm:bg-slate-900">
            <div class="mb-4 flex items-center gap-2">
                <button type="button" class="rounded-md p-1 text-slate-600 hover:bg-slate-100 hover:text-slate-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-slate-400 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-slate-100 dark:focus-visible:ring-slate-500" aria-label="Volver al listado" @click="emit('back')"><ArrowLeft class="size-4" /></button>
                <h2 class="text-base font-semibold">{{ editing ? 'Editar cambio de moneda' : 'Registrar cambio de moneda' }}</h2>
            </div>

            <form class="space-y-3" @submit.prevent="emit('submit')">
                <label v-if="!editing" class="block space-y-1 text-sm">
                    <span class="font-medium">Tipo</span>
                    <select class="w-full rounded-md border border-slate-300 bg-white px-3 py-2 dark:border-slate-700 dark:bg-slate-950" value="exchange" @change="emit('change-type', $event.target.value)">
                        <option value="expense">Gasto</option><option value="income">Ingreso</option><option value="exchange">Cambio de moneda</option>
                    </select>
                </label>

                <div class="grid gap-3 sm:grid-cols-2">
                    <div class="space-y-1 text-sm">
                        <span class="font-medium">Gastás</span>
                        <div class="flex w-full rounded-md">
                            <input v-model="form.source_amount" type="number" min="0.01" step="0.01" required class="min-w-0 flex-1 rounded-l-md border border-r-0 border-slate-300 bg-white px-3 py-2 outline-none focus-visible:ring-2 focus-visible:ring-slate-400 dark:border-slate-700 dark:bg-slate-950 dark:focus-visible:ring-slate-500" aria-label="Importe que gastás" @focus="openAmountEditor('source')" @input="emit('source-input')">
                            <Combobox v-model="sourceCurrency" by="value">
                                <ComboboxAnchor as-child class="w-14"><ComboboxTrigger as-child><button type="button" class="flex w-14 shrink-0 items-center justify-center gap-0.5 rounded-r-md border border-slate-300 bg-white px-1.5 py-2 text-sm font-medium hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-950 dark:hover:bg-slate-900" aria-label="Seleccionar moneda que gastás">{{ sourceCurrency?.symbol ?? 'AR$' }}<ChevronsUpDown class="size-3 text-slate-500 dark:text-slate-400" /></button></ComboboxTrigger></ComboboxAnchor>
                                <ComboboxList class="w-60 border-slate-200 bg-white text-slate-900 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100" align="end"><ComboboxGroup><ComboboxItem v-for="currency in currencies" :key="currency.value" :value="currency">{{ currency.label }}<ComboboxItemIndicator class="ml-auto"><Check class="size-4" /></ComboboxItemIndicator></ComboboxItem></ComboboxGroup></ComboboxList>
                            </Combobox>
                        </div>
                    </div>
                    <div class="space-y-1 text-sm">
                        <span class="font-medium">Recibís</span>
                        <div class="flex w-full rounded-md">
                            <input v-model="form.target_amount" type="number" min="0.01" step="0.01" required class="min-w-0 flex-1 rounded-l-md border border-r-0 border-slate-300 bg-white px-3 py-2 outline-none focus-visible:ring-2 focus-visible:ring-slate-400 dark:border-slate-700 dark:bg-slate-950 dark:focus-visible:ring-slate-500" aria-label="Importe que recibís" @focus="openAmountEditor('target')" @input="emit('target-input')">
                            <Combobox v-model="targetCurrency" by="value">
                                <ComboboxAnchor as-child class="w-14"><ComboboxTrigger as-child><button type="button" class="flex w-14 shrink-0 items-center justify-center gap-0.5 rounded-r-md border border-slate-300 bg-white px-1.5 py-2 text-sm font-medium hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-950 dark:hover:bg-slate-900" aria-label="Seleccionar moneda que recibís">{{ targetCurrency?.symbol ?? 'USD$' }}<ChevronsUpDown class="size-3 text-slate-500 dark:text-slate-400" /></button></ComboboxTrigger></ComboboxAnchor>
                                <ComboboxList class="w-60 border-slate-200 bg-white text-slate-900 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100" align="end"><ComboboxGroup><ComboboxItem v-for="currency in currencies" :key="currency.value" :value="currency">{{ currency.label }}<ComboboxItemIndicator class="ml-auto"><Check class="size-4" /></ComboboxItemIndicator></ComboboxItem></ComboboxGroup></ComboboxList>
                            </Combobox>
                        </div>
                    </div>
                </div>

                <p v-if="hasSuggestion" class="text-xs text-slate-500 dark:text-slate-400">Monto sugerido según tu último cambio entre estas monedas. Podés modificar ambos importes.</p>
                <p v-if="rate" class="text-sm text-slate-600 dark:text-slate-300">Cotización efectiva: 1 {{ form.target_currency }} = {{ rate }} {{ form.source_currency }}</p>
                <label class="block space-y-1 text-sm"><span class="font-medium">Descripción</span><input v-model="form.description" type="text" maxlength="255" required class="w-full rounded-md border border-slate-300 bg-white px-3 py-2 dark:border-slate-700 dark:bg-slate-950"></label>
                <label class="block space-y-1 text-sm"><span class="font-medium">Lugar (opcional)</span><input v-model="form.place" type="text" maxlength="120" class="w-full rounded-md border border-slate-300 bg-white px-3 py-2 dark:border-slate-700 dark:bg-slate-950"></label>

                <div class="space-y-1 text-sm">
                    <span class="font-medium">Categoría egreso</span>
                    <div class="flex flex-wrap gap-2" role="group" aria-label="Seleccionar categoría egreso">
                        <button type="button" class="rounded-full px-3 py-1.5 text-sm font-medium focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-slate-400" :class="selectedExpenseCategory === null ? 'bg-slate-900 text-white dark:bg-slate-100 dark:text-slate-900' : 'bg-slate-100 text-slate-700 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700'" :aria-pressed="selectedExpenseCategory === null" @click="selectedExpenseCategory = null">Sin categoría</button>
                        <button v-for="category in expenseCategoryOptions" :key="category.id" type="button" class="rounded-full px-3 py-1.5 text-sm font-medium focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-slate-400" :class="Number(selectedExpenseCategory?.id) === Number(category.id) ? 'bg-slate-900 text-white dark:bg-slate-100 dark:text-slate-900' : 'bg-slate-100 text-slate-700 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700'" :aria-pressed="Number(selectedExpenseCategory?.id) === Number(category.id)" @click="selectedExpenseCategory = category">{{ category.name }}</button>
                        <span v-if="expenseCategoryOptions.length === 0" class="text-sm text-slate-500 dark:text-slate-400">No hay categorías disponibles.</span>
                    </div>
                </div>

                <div class="space-y-1 text-sm">
                    <span class="font-medium">Categoría ingreso</span>
                    <div class="flex flex-wrap gap-2" role="group" aria-label="Seleccionar categoría ingreso">
                        <button type="button" class="rounded-full px-3 py-1.5 text-sm font-medium focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-slate-400" :class="selectedIncomeCategory === null ? 'bg-slate-900 text-white dark:bg-slate-100 dark:text-slate-900' : 'bg-slate-100 text-slate-700 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700'" :aria-pressed="selectedIncomeCategory === null" @click="selectedIncomeCategory = null">Sin categoría</button>
                        <button v-for="category in incomeCategoryOptions" :key="category.id" type="button" class="rounded-full px-3 py-1.5 text-sm font-medium focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-slate-400" :class="Number(selectedIncomeCategory?.id) === Number(category.id) ? 'bg-slate-900 text-white dark:bg-slate-100 dark:text-slate-900' : 'bg-slate-100 text-slate-700 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700'" :aria-pressed="Number(selectedIncomeCategory?.id) === Number(category.id)" @click="selectedIncomeCategory = category">{{ category.name }}</button>
                        <span v-if="incomeCategoryOptions.length === 0" class="text-sm text-slate-500 dark:text-slate-400">No hay categorías disponibles.</span>
                    </div>
                </div>

                <div class="space-y-1 text-sm">
                    <span class="font-medium">Tags</span>
                    <div class="relative">
                        <div class="flex min-h-10 w-full flex-wrap items-center gap-2 rounded-md border border-slate-300 bg-white px-2 py-1 text-sm shadow-xs focus-within:border-slate-400 focus-within:ring-2 focus-within:ring-slate-400/50 dark:border-slate-700 dark:bg-slate-950 dark:focus-within:border-slate-600 dark:focus-within:ring-slate-500/50">
                            <span v-for="tagId in selectedTagValues" :key="tagId" class="inline-flex h-6 items-center gap-1 rounded bg-slate-100 px-2 text-xs text-slate-700 dark:bg-slate-800 dark:text-slate-200">{{ selectedTagName(tagId) }}<button type="button" class="rounded text-slate-500 hover:text-slate-900 dark:text-slate-400 dark:hover:text-slate-100" :aria-label="`Quitar tag ${selectedTagName(tagId)}`" @click="selectedTagValues = selectedTagValues.filter((selectedTagId) => selectedTagId !== tagId)"><X class="size-3" /></button></span>
                            <input :value="tagSearch" type="text" autocomplete="off" aria-label="Buscar tags" placeholder="Buscar tags..." class="min-h-5 min-w-24 flex-1 bg-transparent px-1 text-sm outline-none placeholder:text-slate-500 dark:placeholder:text-slate-400" @blur="tagInputFocused = false" @focus="tagInputFocused = true" @input="tagSearch = $event.target.value" @keydown.enter.stop.prevent="selectFirstFilteredTag">
                            <button v-if="isTagDropdownOpen" type="button" class="text-xs text-slate-500 hover:text-slate-900 dark:text-slate-400 dark:hover:text-slate-100" @click="tagInputFocused = false">Cerrar</button>
                        </div>
                        <div v-if="isTagDropdownOpen" class="absolute z-10 mt-1 max-h-48 w-full overflow-y-auto rounded-md border border-slate-200 bg-white p-1 shadow-md dark:border-slate-700 dark:bg-slate-900"><button v-for="tag in filteredTags" :key="tag.id" type="button" class="block w-full rounded-sm px-2 py-1.5 text-left text-sm hover:bg-slate-100 dark:hover:bg-slate-800" @mousedown.prevent="selectTag(tag)">{{ tag.name }}</button></div>
                    </div>
                    <span class="text-xs text-slate-500 dark:text-slate-400">Escribe para filtrar y presiona Enter o selecciona una etiqueta.</span>
                </div>

                <label class="block space-y-1 text-sm"><span class="font-medium">Fecha</span><input v-model="form.purchase_date" type="date" required class="w-full rounded-md border border-slate-300 bg-white px-3 py-2 dark:border-slate-700 dark:bg-slate-950"></label>
                <label class="block space-y-1 text-sm"><span class="font-medium">Notas</span><textarea v-model="form.notes" rows="3" maxlength="5000" class="w-full rounded-md border border-slate-300 bg-white px-3 py-2 dark:border-slate-700 dark:bg-slate-950"></textarea></label>
                <div class="grid gap-3 sm:grid-cols-2">
                    <button type="submit" :disabled="saving || deleting || form.source_currency === form.target_currency" class="w-full rounded-md bg-slate-900 px-3 py-2 text-sm font-medium text-white hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-60 dark:bg-slate-100 dark:text-slate-900 dark:hover:bg-slate-200">{{ saving ? 'Guardando...' : (editing ? 'Guardar cambios' : 'Guardar cambio') }}</button>
                    <button v-if="editing" type="button" :disabled="saving || deleting" class="w-full rounded-md border border-red-200 px-3 py-2 text-sm font-medium text-red-700 hover:bg-red-50 disabled:cursor-not-allowed disabled:opacity-60 dark:border-red-900 dark:text-red-400 dark:hover:bg-red-950" @click="emit('delete')">{{ deleting ? 'Eliminando...' : 'Eliminar cambio' }}</button>
                </div>
            </form>
            <AmountEditor :amount="amountEditorField === 'source' ? form.source_amount : form.target_amount" :format-amount="formatAmount" :open="amountEditorField !== null" @close="amountEditorField = null" @update:amount="updateEditedAmount" />
        </article>
    </section>
</template>

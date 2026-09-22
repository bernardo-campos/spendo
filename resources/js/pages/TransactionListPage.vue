<script setup>
import { ArrowLeft, ChevronDown, Filter, Search, SlidersHorizontal } from '@lucide/vue';
import { computed, onMounted, ref } from 'vue';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

const props = defineProps({
    emptyMessage: {
        type: String,
        required: true,
    },
    formatCurrencyAmount: {
        type: Function,
        required: true,
    },
    loading: {
        type: Boolean,
        required: true,
    },
    showPaymentMethodFilter: {
        type: Boolean,
        default: false,
    },
    title: {
        type: String,
        required: true,
    },
    transactions: {
        type: Array,
        required: true,
    },
    collapsedDates: {
        type: Set,
        required: true,
    },
    displayPreferences: {
        type: Object,
        default: () => ({
            show_category: true,
            show_description: true,
            show_cash_payment_method: false,
            show_credit_payment_method: true,
            show_tags: true,
            show_notes: false,
        }),
    },
});

const emit = defineEmits(['back', 'create', 'edit', 'update:collapsed-dates']);

const GROUPING_STORAGE_KEY = 'spendo:transaction-list-grouping';
const groupingDialogOpen = ref(false);
const grouping = ref('day');
const paymentMethodFilter = ref('all');
const paymentMethodFilterDialogOpen = ref(false);
const searchDialogOpen = ref(false);
const searchQuery = ref('');
const searchFields = ref(['amount', 'category', 'description', 'place', 'tags', 'notes']);
const selectedGrouping = ref('day');
const selectedPaymentMethodFilter = ref('all');
const selectedSearchFields = ref([...searchFields.value]);
const selectedSearchQuery = ref('');

const filteredTransactions = computed(() => {
    const paymentMethodFilteredTransactions = !props.showPaymentMethodFilter || paymentMethodFilter.value === 'all'
        ? props.transactions
        : props.transactions.filter((transaction) => transaction.payment_method === paymentMethodFilter.value);

    const normalizedSearchQuery = normalizeSearchValue(searchQuery.value);

    if (normalizedSearchQuery === '') {
        return paymentMethodFilteredTransactions;
    }

    return paymentMethodFilteredTransactions.filter((transaction) => matchesSearch(transaction, normalizedSearchQuery));
});

const paymentMethodFilterLabel = computed(() => ({
    cash: 'Efectivo',
    credit: 'Crédito',
}[paymentMethodFilter.value] ?? 'Ambos'));

const isSearchActive = computed(() => searchQuery.value.trim() !== '');

const groupedTransactions = computed(() => {
    const transactionsByGroup = new Map();

    [...filteredTransactions.value]
        .sort((left, right) => {
            if (left.is_pending !== right.is_pending) {
                return left.is_pending ? -1 : 1;
            }

            if (left.is_pending && right.is_pending) {
                return String(right.queued_at ?? '').localeCompare(String(left.queued_at ?? ''));
            }

            if (grouping.value === 'category') {
                const categoryOrder = (left.category?.name ?? 'Sin categoría')
                    .localeCompare(right.category?.name ?? 'Sin categoría', 'es');

                if (categoryOrder !== 0) {
                    return categoryOrder;
                }
            }

            const purchaseDateOrder = String(right.purchase_date).localeCompare(String(left.purchase_date));

            if (purchaseDateOrder !== 0) {
                return purchaseDateOrder;
            }

            return String(right.created_at ?? '').localeCompare(String(left.created_at ?? ''));
        })
        .forEach((transaction) => {
            const categoryName = transaction.category?.name ?? 'Sin categoría';
            const groupKey = grouping.value === 'category'
                ? `category-${transaction.category?.id ?? 'uncategorized'}`
                : String(transaction.purchase_date).slice(0, 10);
            const group = transactionsByGroup.get(groupKey) ?? {
                key: groupKey,
                label: grouping.value === 'category' ? categoryName : null,
                totals: { ARS: 0, USD: 0 },
                transactions: [],
            };

            const currency = ['ARS', 'USD'].includes(transaction.currency) ? transaction.currency : 'ARS';
            group.totals[currency] += Number.parseFloat(transaction.amount ?? 0) || 0;
            group.transactions.push(transaction);
            transactionsByGroup.set(groupKey, group);
        });

    return [...transactionsByGroup.values()];
});

const formatGroupDate = (value) => new Intl.DateTimeFormat('es-AR', {
    weekday: 'short',
    day: 'numeric',
    month: 'long',
    year: 'numeric',
}).format(new Date(`${value}T00:00:00`)).toLocaleLowerCase('es-AR');

const formatTransactionDate = (value) => {
    const [year, month, day] = String(value).slice(0, 10).split('-');

    return year && month && day ? `${day}/${month}/${year}` : '';
};

const tagNames = (transaction) => (transaction.tags ?? [])
    .map((tag) => tag.name)
    .join(' | ');

const normalizeSearchValue = (value) => String(value ?? '')
    .normalize('NFD')
    .replace(/\p{Diacritic}/gu, '')
    .toLocaleLowerCase('es-AR');

const matchesSearch = (transaction, normalizedSearchQuery) => {
    const searchableValues = {
        amount: [transaction.amount, formatCurrencyAmount(transaction.currency, transaction.amount)],
        category: [transaction.category?.name],
        description: [transaction.description],
        place: [transaction.place],
        tags: [tagNames(transaction)],
        notes: [transaction.notes],
    };

    return searchFields.value.some((field) => searchableValues[field]
        ?.some((value) => normalizeSearchValue(value).includes(normalizedSearchQuery)));
};

const transactionTypeLabel = (transaction) => {
    if (transaction.type === 'income') {
        return 'Ingreso';
    }

    if (transaction.payment_method === 'credit') {
        return props.displayPreferences.show_credit_payment_method ? 'Crédito' : null;
    }

    return props.displayPreferences.show_cash_payment_method ? 'Efectivo' : null;
};

const shouldShowExpenseField = (transaction, preference) => transaction.type !== 'expense' || props.displayPreferences[preference];

const isGroupExpanded = (key) => !props.collapsedDates.has(key);

const visibleCurrencyTotals = (totals) => Object.entries(totals)
    .filter(([currency, amount]) => currency !== 'USD' || Number(amount) !== 0);

const toggleGroup = (key) => {
    const updatedCollapsedDates = new Set(props.collapsedDates);

    if (updatedCollapsedDates.has(key)) {
        updatedCollapsedDates.delete(key);
    } else {
        updatedCollapsedDates.add(key);
    }

    emit('update:collapsed-dates', updatedCollapsedDates);
};

const openGroupingDialog = () => {
    selectedGrouping.value = grouping.value;
    groupingDialogOpen.value = true;
};

const applyGrouping = () => {
    grouping.value = selectedGrouping.value;
    localStorage.setItem(GROUPING_STORAGE_KEY, grouping.value);
    groupingDialogOpen.value = false;
};

const openPaymentMethodFilterDialog = () => {
    selectedPaymentMethodFilter.value = paymentMethodFilter.value;
    paymentMethodFilterDialogOpen.value = true;
};

const applyPaymentMethodFilter = () => {
    paymentMethodFilter.value = selectedPaymentMethodFilter.value;
    paymentMethodFilterDialogOpen.value = false;
};

const openSearchDialog = () => {
    selectedSearchQuery.value = searchQuery.value;
    selectedSearchFields.value = [...searchFields.value];
    searchDialogOpen.value = true;
};

const applySearch = () => {
    searchQuery.value = selectedSearchQuery.value;
    searchFields.value = [...selectedSearchFields.value];
    searchDialogOpen.value = false;
};

onMounted(() => {
    const storedGrouping = localStorage.getItem(GROUPING_STORAGE_KEY);

    if (storedGrouping === 'day' || storedGrouping === 'category') {
        grouping.value = storedGrouping;
        selectedGrouping.value = storedGrouping;
    }
});
</script>

<template>
    <section class="-mx-4 space-y-4 sm:mx-0">
        <div class="flex items-center justify-between gap-2 px-4 pt-4 sm:px-0 sm:pt-0">
            <div class="flex items-center gap-2">
                <button type="button" class="rounded-md p-1 text-slate-600 hover:bg-slate-100 hover:text-slate-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-slate-400 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-slate-100 dark:focus-visible:ring-slate-500" aria-label="Volver al resumen" title="Volver al resumen" @click="emit('back')">
                    <ArrowLeft class="size-4" />
                </button>
                <h2 class="text-base font-semibold">Listado de {{ title.toLowerCase() }}</h2>
            </div>
            <div class="flex shrink-0 items-center gap-2">
                <button v-if="showPaymentMethodFilter" type="button" class="inline-flex items-center gap-2 rounded-md border px-3 py-1 text-sm font-medium focus-visible:outline-none focus-visible:ring-2" :class="paymentMethodFilter === 'all' ? 'border-slate-300 text-slate-700 hover:bg-slate-100 focus-visible:ring-slate-400 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800 dark:focus-visible:ring-slate-500' : 'border-amber-500 bg-amber-50 text-amber-800 hover:bg-amber-100 focus-visible:ring-amber-500 dark:border-amber-400 dark:bg-amber-950/50 dark:text-amber-200 dark:hover:bg-amber-950 dark:focus-visible:ring-amber-400'" :aria-label="paymentMethodFilter === 'all' ? 'Filtrar movimientos' : `Filtro activo: ${paymentMethodFilterLabel}`" :title="paymentMethodFilter === 'all' ? 'Filtrar movimientos' : `Filtro activo: ${paymentMethodFilterLabel}`" @click="openPaymentMethodFilterDialog">
                    <Filter class="size-4" />
                    <span class="hidden sm:inline">{{ paymentMethodFilter === 'all' ? 'Filtrar' : `Filtro: ${paymentMethodFilterLabel}` }}</span>
                </button>
                <button v-if="showPaymentMethodFilter" type="button" class="inline-flex items-center gap-2 rounded-md border px-3 py-1 text-sm font-medium focus-visible:outline-none focus-visible:ring-2" :class="isSearchActive ? 'border-amber-500 bg-amber-50 text-amber-800 hover:bg-amber-100 focus-visible:ring-amber-500 dark:border-amber-400 dark:bg-amber-950/50 dark:text-amber-200 dark:hover:bg-amber-950 dark:focus-visible:ring-amber-400' : 'border-slate-300 text-slate-700 hover:bg-slate-100 focus-visible:ring-slate-400 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800 dark:focus-visible:ring-slate-500'" :aria-label="isSearchActive ? `Búsqueda activa: ${searchQuery}` : 'Buscar movimientos'" :title="isSearchActive ? `Búsqueda activa: ${searchQuery}` : 'Buscar movimientos'" @click="openSearchDialog">
                    <Search class="size-4" />
                    <span class="hidden sm:inline">Buscar</span>
                </button>
                <button type="button" class="inline-flex items-center gap-2 rounded-md border border-slate-300 px-3 py-1 text-sm font-medium text-slate-700 hover:bg-slate-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-slate-400 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800 dark:focus-visible:ring-slate-500" aria-label="Agrupar movimientos" title="Agrupar movimientos" @click="openGroupingDialog">
                    <SlidersHorizontal class="size-4" />
                    <span class="hidden sm:inline">Agrupar</span>
                </button>
            </div>
        </div>

        <div class="rounded-none border-0 bg-transparent p-0 sm:rounded-lg sm:border sm:border-slate-200 sm:bg-slate-50 sm:p-4 dark:sm:border-slate-800 dark:sm:bg-slate-950">
            <p v-if="loading" class="px-4 pt-4 text-sm text-slate-500 sm:px-0 sm:pt-0 dark:text-slate-400">Cargando...</p>
            <p v-else-if="filteredTransactions.length === 0" class="px-4 pt-4 text-sm text-slate-500 sm:px-0 sm:pt-0 dark:text-slate-400">{{ isSearchActive ? 'No hay egresos que coincidan con la búsqueda.' : (paymentMethodFilter === 'all' ? emptyMessage : 'No hay egresos para este filtro.') }}</p>
            <div v-else class="space-y-5">
                <section v-for="group in groupedTransactions" :key="group.key">
                    <button type="button" class="flex w-full items-baseline justify-between gap-4 px-4 text-left sm:px-0" :aria-expanded="isGroupExpanded(group.key)" @click="toggleGroup(group.key)">
                        <span class="flex items-center gap-1 text-sm font-semibold">
                            <ChevronDown class="size-4 transition-transform duration-200" :class="isGroupExpanded(group.key) ? 'rotate-0' : '-rotate-90'" />
                            {{ grouping === 'day' ? formatGroupDate(group.key) : group.label }}
                        </span>
                        <span class="flex shrink-0 flex-col text-right text-sm font-semibold tabular-nums"><span v-for="[currency, amount] in visibleCurrencyTotals(group.totals)" :key="currency">{{ formatCurrencyAmount(currency, amount) }}</span></span>
                    </button>

                    <div class="grid transition-[grid-template-rows] duration-200 ease-out" :class="isGroupExpanded(group.key) ? 'grid-rows-[1fr]' : 'grid-rows-[0fr]'">
                        <div class="overflow-hidden">
                            <div class="mt-0 rounded-none border-b border-slate-200 bg-white px-3 py-3 transition-opacity duration-200 sm:mt-2 sm:rounded-md sm:border sm:border-slate-200 dark:border-slate-800 dark:bg-slate-900 dark:sm:border-slate-800" :class="isGroupExpanded(group.key) ? 'opacity-100' : 'opacity-0'">
                                <ul class="space-y-3">
                                    <li v-for="transaction in group.transactions" :key="transaction.id" class="text-sm">
                                        <button type="button" class="grid w-full grid-cols-[minmax(0,1fr)_auto] gap-x-4 rounded-sm text-left transition-colors hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-slate-400 dark:hover:bg-slate-800 dark:focus-visible:ring-slate-500" @click="emit('edit', transaction)">
                                            <div class="min-w-0">
                                                <p v-if="shouldShowExpenseField(transaction, 'show_category')" class="truncate font-semibold">
                                                    {{ grouping === 'category' ? formatTransactionDate(transaction.purchase_date) : (transaction.category?.name ?? 'Sin categoría') }}
                                                </p>
                                                <p v-if="shouldShowExpenseField(transaction, 'show_tags') && tagNames(transaction)" class="truncate text-xs text-slate-500 dark:text-slate-400">{{ tagNames(transaction) }}</p>
                                                <p v-if="transaction.place" class="truncate text-xs text-slate-500 dark:text-slate-400">{{ transaction.place }}</p>
                                                <p v-if="shouldShowExpenseField(transaction, 'show_description')" class="truncate italic text-slate-500 dark:text-slate-400">{{ transaction.description }}</p>
                                                <p v-if="shouldShowExpenseField(transaction, 'show_notes') && transaction.notes" class="truncate text-xs text-slate-500 dark:text-slate-400">{{ transaction.notes }}</p>
                                                <p v-if="transactionTypeLabel(transaction) || transaction.installment_number" class="text-xs text-slate-500 dark:text-slate-400">
                                                    <template v-if="transactionTypeLabel(transaction)">{{ transactionTypeLabel(transaction) }}</template>
                                                    <template v-if="transaction.installment_number"><span v-if="transactionTypeLabel(transaction)"> · </span>Cuota {{ transaction.installment_number }}/{{ transaction.total_installments }}</template>
                                                </p>
                                            </div>
                                            <span class="self-start whitespace-nowrap tabular-nums text-slate-500 dark:text-slate-400">{{ formatCurrencyAmount(transaction.currency, transaction.amount) }}</span>
                                        </button>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </section>
            </div>

            <div class="sticky bottom-4 mt-4 flex justify-end px-4 pb-4 sm:px-0 sm:pb-0">
                <button type="button" class="rounded-md bg-slate-900 px-3 py-2 text-sm font-medium text-white shadow-sm hover:bg-slate-800 dark:bg-slate-100 dark:text-slate-900 dark:hover:bg-slate-200" @click="emit('create')">
                    Registrar {{ title.toLowerCase().slice(0, -1) }}
                </button>
            </div>
        </div>

        <Dialog v-model:open="groupingDialogOpen">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Agrupar listado</DialogTitle>
                    <DialogDescription>Elegí cómo querés organizar los {{ title.toLowerCase() }}.</DialogDescription>
                </DialogHeader>
                <fieldset class="grid gap-2">
                    <label class="flex cursor-pointer items-center gap-3 rounded-md border border-slate-200 px-3 py-3 text-sm hover:bg-slate-50 dark:border-slate-700 dark:hover:bg-slate-800">
                        <input v-model="selectedGrouping" value="day" type="radio" class="size-4 border-slate-300 text-slate-900 focus:ring-slate-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
                        <span><span class="block font-medium">Por día</span><span class="block text-xs text-slate-500 dark:text-slate-400">Organiza los movimientos según su fecha.</span></span>
                    </label>
                    <label class="flex cursor-pointer items-center gap-3 rounded-md border border-slate-200 px-3 py-3 text-sm hover:bg-slate-50 dark:border-slate-700 dark:hover:bg-slate-800">
                        <input v-model="selectedGrouping" value="category" type="radio" class="size-4 border-slate-300 text-slate-900 focus:ring-slate-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
                        <span><span class="block font-medium">Por categoría</span><span class="block text-xs text-slate-500 dark:text-slate-400">Agrupa los movimientos según su categoría.</span></span>
                    </label>
                </fieldset>
                <DialogFooter>
                    <DialogClose as-child><button type="button" class="rounded-md border border-slate-300 px-3 py-2 text-sm font-medium dark:border-slate-700">Cancelar</button></DialogClose>
                    <button type="button" class="rounded-md bg-slate-900 px-3 py-2 text-sm font-medium text-white hover:bg-slate-800 dark:bg-slate-100 dark:text-slate-900 dark:hover:bg-slate-200" @click="applyGrouping">Aplicar</button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <Dialog v-if="showPaymentMethodFilter" v-model:open="paymentMethodFilterDialogOpen">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Filtrar movimientos</DialogTitle>
                    <DialogDescription>Elegí qué movimientos querés ver en el listado de egresos.</DialogDescription>
                </DialogHeader>
                <fieldset class="grid gap-2">
                    <label class="flex cursor-pointer items-center gap-3 rounded-md border border-slate-200 px-3 py-3 text-sm hover:bg-slate-50 dark:border-slate-700 dark:hover:bg-slate-800">
                        <input v-model="selectedPaymentMethodFilter" value="all" type="radio" class="size-4 border-slate-300 text-slate-900 focus:ring-slate-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
                        <span><span class="block font-medium">Ambos</span><span class="block text-xs text-slate-500 dark:text-slate-400">Muestra movimientos en efectivo y crédito.</span></span>
                    </label>
                    <label class="flex cursor-pointer items-center gap-3 rounded-md border border-slate-200 px-3 py-3 text-sm hover:bg-slate-50 dark:border-slate-700 dark:hover:bg-slate-800">
                        <input v-model="selectedPaymentMethodFilter" value="cash" type="radio" class="size-4 border-slate-300 text-slate-900 focus:ring-slate-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
                        <span><span class="block font-medium">Efectivo</span><span class="block text-xs text-slate-500 dark:text-slate-400">Muestra solamente movimientos pagados en efectivo.</span></span>
                    </label>
                    <label class="flex cursor-pointer items-center gap-3 rounded-md border border-slate-200 px-3 py-3 text-sm hover:bg-slate-50 dark:border-slate-700 dark:hover:bg-slate-800">
                        <input v-model="selectedPaymentMethodFilter" value="credit" type="radio" class="size-4 border-slate-300 text-slate-900 focus:ring-slate-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
                        <span><span class="block font-medium">Crédito</span><span class="block text-xs text-slate-500 dark:text-slate-400">Muestra solamente movimientos pagados con tarjeta de crédito.</span></span>
                    </label>
                </fieldset>
                <DialogFooter>
                    <DialogClose as-child><button type="button" class="rounded-md border border-slate-300 px-3 py-2 text-sm font-medium dark:border-slate-700">Cancelar</button></DialogClose>
                    <button type="button" class="rounded-md bg-slate-900 px-3 py-2 text-sm font-medium text-white hover:bg-slate-800 dark:bg-slate-100 dark:text-slate-900 dark:hover:bg-slate-200" @click="applyPaymentMethodFilter">Aplicar</button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <Dialog v-if="showPaymentMethodFilter" v-model:open="searchDialogOpen">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Buscar egresos</DialogTitle>
                    <DialogDescription>La búsqueda se realiza dentro del período seleccionado.</DialogDescription>
                </DialogHeader>
                <div class="grid gap-4">
                    <label class="grid gap-2 text-sm font-medium">
                        Buscar
                        <input v-model="selectedSearchQuery" type="search" placeholder="Ingresá un valor" class="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-normal dark:border-slate-700 dark:bg-slate-950">
                    </label>
                    <fieldset class="grid gap-2">
                        <legend class="text-sm font-medium">Buscar en</legend>
                        <div class="grid grid-cols-2 gap-2">
                            <label v-for="field in [{ value: 'amount', label: 'Monto' }, { value: 'category', label: 'Categoría' }, { value: 'description', label: 'Descripción' }, { value: 'place', label: 'Lugar' }, { value: 'tags', label: 'Etiquetas' }, { value: 'notes', label: 'Nota' }]" :key="field.value" class="flex cursor-pointer items-center gap-2 rounded-md border border-slate-200 px-3 py-2 text-sm hover:bg-slate-50 dark:border-slate-700 dark:hover:bg-slate-800">
                                <input v-model="selectedSearchFields" :value="field.value" type="checkbox" class="size-4 rounded border-slate-300 text-slate-900 focus:ring-slate-500 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
                                {{ field.label }}
                            </label>
                        </div>
                    </fieldset>
                </div>
                <DialogFooter>
                    <DialogClose as-child><button type="button" class="rounded-md border border-slate-300 px-3 py-2 text-sm font-medium dark:border-slate-700">Cancelar</button></DialogClose>
                    <button type="button" class="rounded-md bg-slate-900 px-3 py-2 text-sm font-medium text-white hover:bg-slate-800 dark:bg-slate-100 dark:text-slate-900 dark:hover:bg-slate-200" @click="applySearch">Aplicar</button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </section>
</template>

<script setup>
import { ArrowLeft, ChevronDown } from '@lucide/vue';
import { computed } from 'vue';

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

const groupedTransactions = computed(() => {
    const transactionsByDate = new Map();

    [...props.transactions]
        .sort((left, right) => {
            const purchaseDateOrder = String(right.purchase_date).localeCompare(String(left.purchase_date));

            if (purchaseDateOrder !== 0) {
                return purchaseDateOrder;
            }

            return String(right.created_at ?? '').localeCompare(String(left.created_at ?? ''));
        })
        .forEach((transaction) => {
            const date = String(transaction.purchase_date).slice(0, 10);
            const group = transactionsByDate.get(date) ?? {
                date,
                totals: { ARS: 0, USD: 0 },
                transactions: [],
            };

            const currency = ['ARS', 'USD'].includes(transaction.currency) ? transaction.currency : 'ARS';
            group.totals[currency] += Number.parseFloat(transaction.amount ?? 0) || 0;
            group.transactions.push(transaction);
            transactionsByDate.set(date, group);
        });

    return [...transactionsByDate.values()];
});

const formatGroupDate = (value) => new Intl.DateTimeFormat('es-AR', {
    weekday: 'short',
    day: 'numeric',
    month: 'long',
    year: 'numeric',
}).format(new Date(`${value}T00:00:00`)).toLocaleLowerCase('es-AR');

const tagNames = (transaction) => (transaction.tags ?? [])
    .map((tag) => tag.name)
    .join(' | ');

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

const isGroupExpanded = (date) => !props.collapsedDates.has(date);

const visibleCurrencyTotals = (totals) => Object.entries(totals)
    .filter(([currency, amount]) => currency !== 'USD' || Number(amount) !== 0);

const toggleGroup = (date) => {
    const updatedCollapsedDates = new Set(props.collapsedDates);

    if (updatedCollapsedDates.has(date)) {
        updatedCollapsedDates.delete(date);
    } else {
        updatedCollapsedDates.add(date);
    }

    emit('update:collapsed-dates', updatedCollapsedDates);
};
</script>

<template>
    <section class="-mx-4 space-y-4 sm:mx-0">
        <div class="flex items-center gap-2 px-4 pt-4 sm:px-0 sm:pt-0">
            <button type="button" class="rounded-md p-1 text-slate-600 hover:bg-slate-100 hover:text-slate-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-slate-400 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-slate-100 dark:focus-visible:ring-slate-500" aria-label="Volver al resumen" title="Volver al resumen" @click="emit('back')">
                <ArrowLeft class="size-4" />
            </button>
            <h2 class="text-base font-semibold">Listado de {{ title.toLowerCase() }}</h2>
        </div>

        <div class="rounded-none border-0 bg-transparent p-0 sm:rounded-lg sm:border sm:border-slate-200 sm:bg-slate-50 sm:p-4 dark:sm:border-slate-800 dark:sm:bg-slate-950">
            <p v-if="loading" class="px-4 pt-4 text-sm text-slate-500 sm:px-0 sm:pt-0 dark:text-slate-400">Cargando...</p>
            <p v-else-if="transactions.length === 0" class="px-4 pt-4 text-sm text-slate-500 sm:px-0 sm:pt-0 dark:text-slate-400">{{ emptyMessage }}</p>
            <div v-else class="space-y-5">
                <section v-for="group in groupedTransactions" :key="group.date">
                    <button type="button" class="flex w-full items-baseline justify-between gap-4 px-4 text-left sm:px-0" :aria-expanded="isGroupExpanded(group.date)" @click="toggleGroup(group.date)">
                        <span class="flex items-center gap-1 text-sm font-semibold">
                            <ChevronDown class="size-4 transition-transform duration-200" :class="isGroupExpanded(group.date) ? 'rotate-0' : '-rotate-90'" />
                            {{ formatGroupDate(group.date) }}
                        </span>
                        <span class="flex shrink-0 flex-col text-right text-sm font-semibold tabular-nums"><span v-for="[currency, amount] in visibleCurrencyTotals(group.totals)" :key="currency">{{ formatCurrencyAmount(currency, amount) }}</span></span>
                    </button>

                    <div class="grid transition-[grid-template-rows] duration-200 ease-out" :class="isGroupExpanded(group.date) ? 'grid-rows-[1fr]' : 'grid-rows-[0fr]'">
                        <div class="overflow-hidden">
                            <div class="mt-0 rounded-none border-b border-slate-200 bg-white px-3 py-3 transition-opacity duration-200 sm:mt-2 sm:rounded-md sm:border sm:border-slate-200 dark:border-slate-800 dark:bg-slate-900 dark:sm:border-slate-800" :class="isGroupExpanded(group.date) ? 'opacity-100' : 'opacity-0'">
                                <ul class="space-y-3">
                                    <li v-for="transaction in group.transactions" :key="transaction.id" class="text-sm">
                                        <button type="button" class="grid w-full grid-cols-[minmax(0,1fr)_auto] gap-x-4 rounded-sm text-left transition-colors hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-slate-400 dark:hover:bg-slate-800 dark:focus-visible:ring-slate-500" @click="emit('edit', transaction)">
                                            <div class="min-w-0">
                                                <p v-if="shouldShowExpenseField(transaction, 'show_category')" class="truncate font-semibold">
                                                    {{ transaction.category?.name ?? 'Sin categoría' }}
                                                </p>
                                                <p v-if="shouldShowExpenseField(transaction, 'show_tags') && tagNames(transaction)" class="truncate text-xs text-slate-500 dark:text-slate-400">{{ tagNames(transaction) }}</p>
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
    </section>
</template>

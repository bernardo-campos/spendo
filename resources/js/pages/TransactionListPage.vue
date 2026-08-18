<script setup>
import { ChevronDown } from '@lucide/vue';
import { computed, ref } from 'vue';

const props = defineProps({
    currencySymbol: {
        type: String,
        required: true,
    },
    emptyMessage: {
        type: String,
        required: true,
    },
    formatAmount: {
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
});

const emit = defineEmits(['create']);
const collapsedDates = ref(new Set());

const groupedTransactions = computed(() => {
    const transactionsByDate = new Map();

    [...props.transactions]
        .sort((left, right) => String(right.purchase_date).localeCompare(String(left.purchase_date)))
        .forEach((transaction) => {
            const date = String(transaction.purchase_date).slice(0, 10);
            const group = transactionsByDate.get(date) ?? {
                date,
                total: 0,
                transactions: [],
            };

            group.total += Number.parseFloat(transaction.amount ?? 0) || 0;
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

    return transaction.payment_method === 'credit' ? 'Crédito' : 'Efectivo';
};

const isGroupExpanded = (date) => !collapsedDates.value.has(date);

const toggleGroup = (date) => {
    const updatedCollapsedDates = new Set(collapsedDates.value);

    if (updatedCollapsedDates.has(date)) {
        updatedCollapsedDates.delete(date);
    } else {
        updatedCollapsedDates.add(date);
    }

    collapsedDates.value = updatedCollapsedDates;
};
</script>

<template>
    <section class="space-y-4">
        <h2 class="text-base font-semibold">Listado de {{ title.toLowerCase() }}</h2>

        <div class="rounded-lg border border-slate-200 bg-slate-50 p-4 dark:border-slate-800 dark:bg-slate-950">
            <p v-if="loading" class="text-sm text-slate-500 dark:text-slate-400">Cargando...</p>
            <p v-else-if="transactions.length === 0" class="text-sm text-slate-500 dark:text-slate-400">{{ emptyMessage }}</p>
            <div v-else class="space-y-5">
                <section v-for="group in groupedTransactions" :key="group.date">
                    <button type="button" class="flex w-full items-baseline justify-between gap-4 text-left" :aria-expanded="isGroupExpanded(group.date)" @click="toggleGroup(group.date)">
                        <span class="flex items-center gap-1 text-sm font-semibold">
                            <ChevronDown class="size-4 transition-transform duration-200" :class="isGroupExpanded(group.date) ? 'rotate-0' : '-rotate-90'" />
                            {{ formatGroupDate(group.date) }}
                        </span>
                        <span class="shrink-0 text-sm font-semibold tabular-nums">{{ currencySymbol }}{{ formatAmount(group.total) }}</span>
                    </button>

                    <div class="grid transition-[grid-template-rows] duration-200 ease-out" :class="isGroupExpanded(group.date) ? 'grid-rows-[1fr]' : 'grid-rows-[0fr]'">
                        <div class="overflow-hidden">
                            <div class="mt-2 rounded-md border border-slate-200 bg-white px-3 py-3 transition-opacity duration-200 dark:border-slate-800 dark:bg-slate-900" :class="isGroupExpanded(group.date) ? 'opacity-100' : 'opacity-0'">
                                <ul class="space-y-3">
                                    <li v-for="transaction in group.transactions" :key="transaction.id" class="grid grid-cols-[minmax(0,1fr)_auto] gap-x-4 text-sm">
                                        <div class="min-w-0">
                                            <p class="truncate font-semibold">
                                                {{ transaction.category?.name ?? 'Sin categoría' }}
                                                <span v-if="tagNames(transaction)" class="ml-1 text-xs font-normal text-slate-500 dark:text-slate-400">{{ tagNames(transaction) }}</span>
                                            </p>
                                            <p class="truncate italic text-slate-500 dark:text-slate-400">{{ transaction.description }}</p>
                                            <p class="text-xs text-slate-500 dark:text-slate-400">
                                                {{ transactionTypeLabel(transaction) }}
                                                <template v-if="transaction.installment_number"> · Cuota {{ transaction.installment_number }}/{{ transaction.total_installments }}</template>
                                            </p>
                                        </div>
                                        <span class="self-start whitespace-nowrap tabular-nums text-slate-500 dark:text-slate-400">{{ currencySymbol }}{{ formatAmount(transaction.amount) }}</span>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </section>
            </div>

            <div class="sticky bottom-4 mt-4 flex justify-end">
                <button type="button" class="rounded-md bg-slate-900 px-3 py-2 text-sm font-medium text-white shadow-sm hover:bg-slate-800 dark:bg-slate-100 dark:text-slate-900 dark:hover:bg-slate-200" @click="emit('create')">
                    Registrar {{ title.toLowerCase().slice(0, -1) }}
                </button>
            </div>
        </div>
    </section>
</template>

<script setup>
import { Plus } from '@lucide/vue';

defineProps({
    cardsSummary: {
        type: Array,
        required: true,
    },
    currencySymbol: {
        type: String,
        required: true,
    },
    formatAmount: {
        type: Function,
        required: true,
    },
    formatDate: {
        type: Function,
        required: true,
    },
    loading: {
        type: Boolean,
        required: true,
    },
    recentTransactions: {
        type: Array,
        required: true,
    },
});

const emit = defineEmits(['create-expense']);
</script>

<template>
    <section class="grid gap-4 md:grid-cols-3">
        <article v-for="card in cardsSummary" :key="card.title" class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
            <h2 class="text-sm font-medium text-slate-500 dark:text-slate-400">{{ card.title }}</h2>
            <p class="mt-2 text-2xl font-semibold">{{ card.value }}</p>
        </article>
    </section>

    <section class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
        <h2 class="mb-4 text-base font-semibold">Últimos movimientos</h2>
        <p v-if="loading" class="text-sm text-slate-500 dark:text-slate-400">Cargando...</p>
        <p v-else-if="recentTransactions.length === 0" class="text-sm text-slate-500 dark:text-slate-400">Aún no hay transacciones.</p>
        <ul v-else class="space-y-2">
            <li v-for="transaction in recentTransactions" :key="transaction.id" class="flex items-center justify-between rounded-md border border-slate-200 px-3 py-2 text-sm dark:border-slate-800">
                <div>
                    <p class="font-medium">{{ transaction.description }}</p>
                    <p class="text-xs text-slate-500 dark:text-slate-400">{{ transaction.type === 'expense' ? 'Gasto' : 'Ingreso' }} · {{ formatDate(transaction.purchase_date) }}</p>
                </div>
                <span class="font-semibold">{{ currencySymbol }}{{ formatAmount(transaction.amount) }}</span>
            </li>
        </ul>
    </section>

    <div class="sticky bottom-4 mt-4 flex justify-end">
        <button type="button" class="flex size-12 items-center justify-center rounded-full bg-slate-900 text-2xl font-medium leading-none text-white shadow-md hover:bg-slate-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-slate-400 focus-visible:ring-offset-2 dark:bg-slate-100 dark:text-slate-900 dark:hover:bg-slate-200 dark:focus-visible:ring-slate-500 dark:focus-visible:ring-offset-slate-950" aria-label="Registrar egreso" title="Registrar egreso" @click="emit('create-expense')">
            <Plus class="size-6" :stroke-width="2.5" aria-hidden="true" />
        </button>
    </div>
</template>

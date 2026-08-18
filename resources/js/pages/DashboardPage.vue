<script setup>
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
</template>

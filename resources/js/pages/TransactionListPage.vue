<script setup>
defineProps({
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
    formatDate: {
        type: Function,
        required: true,
    },
    loading: {
        type: Boolean,
        required: true,
    },
    selectedPeriod: {
        type: String,
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

const emit = defineEmits(['create', 'update:selected-period']);
</script>

<template>
    <section class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
        <div class="mb-4 flex items-center justify-between gap-3">
            <h2 class="text-base font-semibold">Listado de {{ title.toLowerCase() }}</h2>
            <input :value="selectedPeriod" type="month" class="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-950" @input="emit('update:selected-period', $event.target.value)">
        </div>

        <p v-if="loading" class="text-sm text-slate-500 dark:text-slate-400">Cargando...</p>
        <p v-else-if="transactions.length === 0" class="text-sm text-slate-500 dark:text-slate-400">{{ emptyMessage }}</p>
        <ul v-else class="space-y-2">
            <li v-for="transaction in transactions" :key="transaction.id" class="flex items-center justify-between rounded-md border border-slate-200 px-3 py-2 text-sm dark:border-slate-800">
                <div>
                    <p class="font-medium">{{ transaction.description }}</p>
                    <p class="text-xs text-slate-500 dark:text-slate-400">
                        {{ formatDate(transaction.purchase_date) }}
                        <template v-if="transaction.type === 'expense'"> · {{ transaction.payment_method === 'credit' ? 'Crédito' : 'Efectivo' }}</template>
                    </p>
                </div>
                <span class="font-semibold">{{ currencySymbol }}{{ formatAmount(transaction.amount) }}</span>
            </li>
        </ul>

        <div class="sticky bottom-4 mt-4 flex justify-end">
            <button type="button" class="rounded-md bg-slate-900 px-3 py-2 text-sm font-medium text-white shadow-sm hover:bg-slate-800 dark:bg-slate-100 dark:text-slate-900 dark:hover:bg-slate-200" @click="emit('create')">
                Registrar {{ title.toLowerCase().slice(0, -1) }}
            </button>
        </div>
    </section>
</template>

<script setup>
defineProps({
    cards: { type: Array, required: true },
    categories: { type: Array, required: true },
    categoryOptions: { type: Array, required: true },
    firstInstallmentPaymentDate: { type: String, default: null },
    firstInstallmentPaymentDateIsEstimated: { type: Boolean, required: true },
    forcedTransactionType: { type: String, default: null },
    form: { type: Object, required: true },
    formatDate: { type: Function, required: true },
    isCreditPayment: { type: Boolean, required: true },
    paymentMethods: { type: Array, required: true },
    saving: { type: Boolean, required: true },
    showInstallments: { type: Boolean, required: true },
    tags: { type: Array, required: true },
    title: { type: String, required: true },
});

const emit = defineEmits(['submit']);
</script>

<template>
    <section class="grid gap-6">
        <article class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
            <h2 class="mb-4 text-base font-semibold">{{ title }}</h2>
            <form class="space-y-3" @submit.prevent="emit('submit')">
                <div class="grid gap-3 sm:grid-cols-2">
                    <label v-if="!forcedTransactionType" class="space-y-1 text-sm">
                        <span class="font-medium">Tipo</span>
                        <select v-model="form.type" class="w-full rounded-md border border-slate-300 bg-white px-3 py-2 dark:border-slate-700 dark:bg-slate-950">
                            <option value="expense">Gasto</option>
                            <option value="income">Ingreso</option>
                        </select>
                    </label>
                    <label class="space-y-1 text-sm">
                        <span class="font-medium">Monto</span>
                        <input v-model="form.amount" type="number" min="0" step="0.01" required class="w-full rounded-md border border-slate-300 bg-white px-3 py-2 dark:border-slate-700 dark:bg-slate-950">
                    </label>
                </div>

                <label class="space-y-1 text-sm">
                    <span class="font-medium">Descripción</span>
                    <input v-model="form.description" type="text" required class="w-full rounded-md border border-slate-300 bg-white px-3 py-2 dark:border-slate-700 dark:bg-slate-950">
                </label>

                <label class="space-y-1 text-sm">
                    <span class="font-medium">Categoría</span>
                    <select v-model="form.category_id" class="w-full rounded-md border border-slate-300 bg-white px-3 py-2 dark:border-slate-700 dark:bg-slate-950">
                        <option value="">Sin categoría</option>
                        <option v-for="category in categoryOptions" :key="category.id" :value="category.id">{{ category.name }}</option>
                    </select>
                </label>

                <label class="space-y-1 text-sm">
                    <span class="font-medium">Tags</span>
                    <select v-model="form.tag_ids" multiple class="w-full rounded-md border border-slate-300 bg-white px-3 py-2 dark:border-slate-700 dark:bg-slate-950">
                        <option v-for="tag in tags" :key="tag.id" :value="tag.id">{{ tag.name }}</option>
                    </select>
                    <span class="text-xs text-slate-500 dark:text-slate-400">Mantén Ctrl/Cmd para seleccionar varios.</span>
                </label>

                <label class="space-y-1 text-sm">
                    <span class="font-medium">Fecha</span>
                    <input v-model="form.purchase_date" type="date" required class="w-full rounded-md border border-slate-300 bg-white px-3 py-2 dark:border-slate-700 dark:bg-slate-950">
                </label>

                <label v-if="form.type === 'expense'" class="space-y-1 text-sm">
                    <span class="font-medium">Forma de pago</span>
                    <select v-model="form.payment_method" required class="w-full rounded-md border border-slate-300 bg-white px-3 py-2 dark:border-slate-700 dark:bg-slate-950">
                        <option v-for="paymentMethod in paymentMethods" :key="paymentMethod.value" :value="paymentMethod.value">{{ paymentMethod.label }}</option>
                    </select>
                </label>

                <label v-if="isCreditPayment" class="space-y-1 text-sm">
                    <span class="font-medium">Tarjeta</span>
                    <select v-model="form.card_id" required class="w-full rounded-md border border-slate-300 bg-white px-3 py-2 dark:border-slate-700 dark:bg-slate-950">
                        <option value="" disabled>Selecciona una tarjeta</option>
                        <option v-for="card in cards" :key="card.id" :value="card.id">{{ card.name }} · ****{{ card.last_four_digits }}</option>
                    </select>
                </label>

                <p v-if="firstInstallmentPaymentDate" class="text-xs text-slate-500 dark:text-slate-400">
                    La primera cuota se pagará el {{ formatDate(firstInstallmentPaymentDate) }}
                    {{ firstInstallmentPaymentDateIsEstimated ? '(fecha estimada)' : '(fecha real por ciclo cargado)' }}.
                </p>

                <label v-if="isCreditPayment" class="space-y-1 text-sm">
                    <span class="font-medium">Cuotas</span>
                    <input v-model.number="form.installments_count" type="number" min="1" max="120" required class="w-full rounded-md border border-slate-300 bg-white px-3 py-2 dark:border-slate-700 dark:bg-slate-950">
                </label>

                <p v-if="showInstallments" class="text-xs text-slate-500 dark:text-slate-400">La fecha de pago se calcula automáticamente según cierre/vencimiento de la tarjeta.</p>

                <label class="space-y-1 text-sm">
                    <span class="font-medium">Notas</span>
                    <textarea v-model="form.notes" rows="3" class="w-full rounded-md border border-slate-300 bg-white px-3 py-2 dark:border-slate-700 dark:bg-slate-950"></textarea>
                </label>

                <button type="submit" :disabled="saving" class="w-full rounded-md bg-slate-900 px-3 py-2 text-sm font-medium text-white hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-60 dark:bg-slate-100 dark:text-slate-900 dark:hover:bg-slate-200">{{ saving ? 'Guardando...' : 'Guardar transacción' }}</button>
            </form>
        </article>
    </section>
</template>

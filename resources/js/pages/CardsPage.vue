<script setup>
defineProps({
    billingCycleForms: { type: Object, required: true },
    cardForm: { type: Object, required: true },
    cards: { type: Array, required: true },
    formatDate: { type: Function, required: true },
    getBillingCycleForm: { type: Function, required: true },
    savingBillingCycle: { type: Boolean, required: true },
    savingCard: { type: Boolean, required: true },
});

const emit = defineEmits(['edit-billing-cycle', 'edit-card', 'remove-card', 'reset-billing-cycle', 'reset-card', 'submit-billing-cycle', 'submit-card']);
</script>

<template>
    <section class="grid gap-6">
        <article class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
            <h2 class="mb-4 text-base font-semibold">Tarjetas</h2>
            <p class="mb-4 text-xs text-slate-500 dark:text-slate-400">Las formas de pago son fijas en el sistema: Efectivo y Crédito.</p>

            <form class="grid gap-3 sm:grid-cols-2" @submit.prevent="emit('submit-card')">
                <label class="space-y-1 text-sm"><span class="font-medium">Nombre</span><input v-model="cardForm.name" type="text" required class="w-full rounded-md border border-slate-300 bg-white px-3 py-2 dark:border-slate-700 dark:bg-slate-950"></label>
                <label class="space-y-1 text-sm"><span class="font-medium">Últimos 4 dígitos</span><input v-model="cardForm.last_four_digits" type="text" maxlength="4" required class="w-full rounded-md border border-slate-300 bg-white px-3 py-2 dark:border-slate-700 dark:bg-slate-950"></label>
                <label class="space-y-1 text-sm"><span class="font-medium">Día de cierre</span><input v-model="cardForm.closing_day" type="number" min="1" max="31" required class="w-full rounded-md border border-slate-300 bg-white px-3 py-2 dark:border-slate-700 dark:bg-slate-950"></label>
                <label class="space-y-1 text-sm"><span class="font-medium">Día de vencimiento</span><input v-model="cardForm.due_day" type="number" min="1" max="31" required class="w-full rounded-md border border-slate-300 bg-white px-3 py-2 dark:border-slate-700 dark:bg-slate-950"></label>
                <div class="sm:col-span-2 flex gap-2"><button type="submit" :disabled="savingCard" class="flex-1 rounded-md bg-slate-900 px-3 py-2 text-sm font-medium text-white hover:bg-slate-800 dark:bg-slate-100 dark:text-slate-900 dark:hover:bg-slate-200">{{ cardForm.id === null ? 'Crear' : 'Actualizar' }}</button><button type="button" class="rounded-md border border-slate-300 px-3 py-2 text-sm dark:border-slate-700" @click="emit('reset-card')">Limpiar</button></div>
            </form>

            <ul class="mt-4 space-y-2">
                <li v-for="card in cards" :key="card.id" class="rounded-md border border-slate-200 px-3 py-3 text-sm dark:border-slate-800">
                    <div class="flex items-center justify-between">
                        <div><p class="font-medium">{{ card.name }}</p><p class="text-xs text-slate-500 dark:text-slate-400">****{{ card.last_four_digits }} · Cierre estimado {{ card.closing_day }} · Vence estimado {{ card.due_day }}</p></div>
                        <div class="flex gap-2"><button type="button" class="rounded-md border border-slate-300 px-2 py-1 text-xs dark:border-slate-700" @click="emit('edit-card', card)">Editar</button><button type="button" class="rounded-md border border-red-300 px-2 py-1 text-xs text-red-700" @click="emit('remove-card', card.id)">Eliminar</button></div>
                    </div>

                    <div class="mt-3 rounded-md border border-slate-200 p-3 dark:border-slate-800">
                        <p class="mb-2 text-xs font-medium text-slate-600 dark:text-slate-300">Ciclos de facturación (reales)</p>
                        <p v-if="!Array.isArray(card.billing_cycles) || card.billing_cycles.length === 0" class="mb-2 text-xs text-slate-500 dark:text-slate-400">No hay ciclos cargados para esta tarjeta.</p>
                        <ul v-else class="mb-3 space-y-1"><li v-for="cycle in card.billing_cycles" :key="cycle.id" class="flex items-center justify-between rounded-md border border-slate-200 px-2 py-1 text-xs dark:border-slate-700"><span>Cierre {{ formatDate(cycle.closing_date) }} · Vence {{ formatDate(cycle.due_date) }}</span><button type="button" class="rounded-md border border-slate-300 px-2 py-1 text-xs dark:border-slate-700" @click="emit('edit-billing-cycle', card.id, cycle)">Editar ciclo</button></li></ul>
                        <form class="grid gap-2 sm:grid-cols-3" @submit.prevent="emit('submit-billing-cycle', card.id)">
                            <input v-model="getBillingCycleForm(card.id).closing_date" type="date" required class="rounded-md border border-slate-300 bg-white px-3 py-2 text-xs dark:border-slate-700 dark:bg-slate-950">
                            <input v-model="getBillingCycleForm(card.id).due_date" type="date" required class="rounded-md border border-slate-300 bg-white px-3 py-2 text-xs dark:border-slate-700 dark:bg-slate-950">
                            <div class="flex gap-2"><button type="submit" :disabled="savingBillingCycle" class="flex-1 rounded-md bg-slate-900 px-3 py-2 text-xs font-medium text-white hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-60 dark:bg-slate-100 dark:text-slate-900 dark:hover:bg-slate-200">{{ getBillingCycleForm(card.id).id === null ? 'Agregar ciclo' : 'Actualizar ciclo' }}</button><button type="button" class="rounded-md border border-slate-300 px-3 py-2 text-xs dark:border-slate-700" @click="emit('reset-billing-cycle', card.id)">Limpiar</button></div>
                        </form>
                    </div>
                </li>
            </ul>
        </article>
    </section>
</template>

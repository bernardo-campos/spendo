<script setup>
import { ref } from 'vue';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { billingCyclesAroundToday, billingCyclesForCard } from '@/utils/billingCycles';

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
const showCardForm = ref(false);
const cyclesDialogOpen = ref(false);
const selectedCardForCycles = ref(null);

const openAllCycles = (card) => {
    selectedCardForCycles.value = card;
    cyclesDialogOpen.value = true;
};

const openNewCardForm = () => {
    emit('reset-card');
    showCardForm.value = true;
};

const editCard = (card) => {
    emit('edit-card', card);
    showCardForm.value = true;
};

const closeCardForm = () => {
    emit('reset-card');
    showCardForm.value = false;
};
</script>

<template>
    <section class="grid gap-6">
        <h2 class="text-base font-semibold">Tarjetas</h2>
        <p class="text-xs text-slate-500 dark:text-slate-400">Las formas de pago son fijas en el sistema: Efectivo y Crédito.</p>

        <button v-if="!showCardForm" type="button" class="justify-self-start rounded-md bg-slate-900 px-3 py-2 text-sm font-medium text-white hover:bg-slate-800 dark:bg-slate-100 dark:text-slate-900 dark:hover:bg-slate-200" @click="openNewCardForm">
            Nueva tarjeta
        </button>

        <div v-if="showCardForm" class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
            <form class="grid gap-3 sm:grid-cols-2" @submit.prevent="emit('submit-card')">
                <label class="block space-y-1 text-sm"><span class="font-medium">Nombre</span><input v-model="cardForm.name" type="text" required class="w-full rounded-md border border-slate-300 bg-white px-3 py-2 dark:border-slate-700 dark:bg-slate-950"></label>
                <label class="block space-y-1 text-sm"><span class="font-medium">Últimos 4 dígitos</span><input v-model="cardForm.last_four_digits" type="text" maxlength="4" required class="w-full rounded-md border border-slate-300 bg-white px-3 py-2 dark:border-slate-700 dark:bg-slate-950"></label>
                <label class="block space-y-1 text-sm"><span class="font-medium">Día de cierre</span><input v-model="cardForm.closing_day" type="number" min="1" max="31" required class="w-full rounded-md border border-slate-300 bg-white px-3 py-2 dark:border-slate-700 dark:bg-slate-950"></label>
                <label class="block space-y-1 text-sm"><span class="font-medium">Día de vencimiento</span><input v-model="cardForm.due_day" type="number" min="1" max="31" required class="w-full rounded-md border border-slate-300 bg-white px-3 py-2 dark:border-slate-700 dark:bg-slate-950"></label>
                <div class="flex gap-2 sm:col-span-2"><button type="submit" :disabled="savingCard" class="flex-1 rounded-md bg-slate-900 px-3 py-2 text-sm font-medium text-white hover:bg-slate-800 dark:bg-slate-100 dark:text-slate-900 dark:hover:bg-slate-200">{{ cardForm.id === null ? 'Crear' : 'Actualizar' }}</button><button type="button" class="rounded-md border border-slate-300 px-3 py-2 text-sm dark:border-slate-700" @click="closeCardForm">Cancelar</button></div>
            </form>
        </div>

        <div v-for="card in cards" :key="card.id" class="rounded-lg border border-slate-200 bg-white p-4 text-sm dark:border-slate-800 dark:bg-slate-900">
            <header class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <p class="font-medium">{{ card.name }}</p>
                    <p class="text-xs text-slate-500 dark:text-slate-400">****{{ card.last_four_digits }} · Cierre estimado {{ card.closing_day }} · Vence estimado {{ card.due_day }}</p>
                </div>
                <span class="flex gap-2"><button type="button" class="rounded-md border border-slate-300 px-2 py-1 text-xs dark:border-slate-700" @click="editCard(card)">Editar</button><button type="button" class="rounded-md border border-red-300 px-2 py-1 text-xs text-red-700" @click="emit('remove-card', card.id)">Eliminar</button></span>
            </header>

            <section class="mt-3 border-t border-slate-200 pt-3 dark:border-slate-800">
                <div class="mb-2 flex items-center justify-between gap-3">
                    <p class="text-xs font-medium text-slate-600 dark:text-slate-300">Ciclos de facturación</p>
                    <button v-if="billingCyclesForCard(card).length > billingCyclesAroundToday(card).length" type="button" class="text-xs font-medium text-slate-600 underline underline-offset-2 hover:text-slate-900 dark:text-slate-300 dark:hover:text-slate-100" @click="openAllCycles(card)">Ver todos</button>
                </div>
                <p v-if="billingCyclesAroundToday(card).length === 0" class="mb-2 text-xs text-slate-500 dark:text-slate-400">No fue posible calcular ciclos para esta tarjeta.</p>
                <ul v-else class="mb-3 space-y-1"><li v-for="entry in billingCyclesAroundToday(card)" :key="entry.cycle.id" class="flex items-center justify-between gap-3 rounded-md border border-slate-200 px-2 py-1 text-xs dark:border-slate-700"><span><span class="mr-1 text-slate-500 dark:text-slate-400">{{ entry.label }}:</span>Cierre {{ formatDate(entry.cycle.closing_date) }} · Vence {{ formatDate(entry.cycle.due_date) }} <span v-if="entry.cycle.is_estimated" class="text-slate-500 dark:text-slate-400">(estimado)</span></span><button v-if="!entry.cycle.is_estimated" type="button" class="shrink-0 rounded-md border border-slate-300 px-2 py-1 text-xs dark:border-slate-700" @click="emit('edit-billing-cycle', card.id, entry.cycle)">Editar ciclo</button></li></ul>
                <form class="grid gap-2 sm:grid-cols-3" @submit.prevent="emit('submit-billing-cycle', card.id)">
                    <input v-model="getBillingCycleForm(card.id).closing_date" type="date" required class="rounded-md border border-slate-300 bg-white px-3 py-2 text-xs dark:border-slate-700 dark:bg-slate-950">
                    <input v-model="getBillingCycleForm(card.id).due_date" type="date" required class="rounded-md border border-slate-300 bg-white px-3 py-2 text-xs dark:border-slate-700 dark:bg-slate-950">
                    <div class="flex gap-2"><button type="submit" :disabled="savingBillingCycle" class="flex-1 rounded-md bg-slate-900 px-3 py-2 text-xs font-medium text-white hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-60 dark:bg-slate-100 dark:text-slate-900 dark:hover:bg-slate-200">{{ getBillingCycleForm(card.id).id === null ? 'Agregar ciclo' : 'Actualizar ciclo' }}</button><button type="button" class="rounded-md border border-slate-300 px-3 py-2 text-xs dark:border-slate-700" @click="emit('reset-billing-cycle', card.id)">Limpiar</button></div>
                </form>
            </section>
        </div>

        <Dialog v-model:open="cyclesDialogOpen">
            <DialogContent class="max-h-[80vh] overflow-y-auto sm:max-w-xl">
                <DialogHeader>
                    <DialogTitle>Todos los ciclos de facturación</DialogTitle>
                    <DialogDescription>{{ selectedCardForCycles?.name ?? '' }}</DialogDescription>
                </DialogHeader>
                <ul class="space-y-2">
                    <li v-for="cycle in selectedCardForCycles ? billingCyclesForCard(selectedCardForCycles) : []" :key="cycle.id" class="flex items-center justify-between gap-3 rounded-md border border-slate-200 px-3 py-2 text-sm dark:border-slate-700">
                        <span>Cierre {{ formatDate(cycle.closing_date) }} · Vence {{ formatDate(cycle.due_date) }} <span v-if="cycle.is_estimated" class="text-xs text-slate-500 dark:text-slate-400">(estimado)</span></span>
                        <button v-if="!cycle.is_estimated" type="button" class="shrink-0 rounded-md border border-slate-300 px-2 py-1 text-xs dark:border-slate-700" @click="emit('edit-billing-cycle', selectedCardForCycles.id, cycle); cyclesDialogOpen = false">Editar ciclo</button>
                    </li>
                </ul>
                <DialogFooter>
                    <DialogClose as-child><button type="button" class="rounded-md border border-slate-300 px-3 py-2 text-sm dark:border-slate-700">Cerrar</button></DialogClose>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </section>
</template>

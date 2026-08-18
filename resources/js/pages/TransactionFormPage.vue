<script setup>
import { ArrowLeft, Check, ChevronsUpDown } from '@lucide/vue';
import { computed, ref } from 'vue';
import {
    TagsInput,
    TagsInputInput,
    TagsInputItem,
    TagsInputItemDelete,
    TagsInputItemText,
} from '@/components/ui/tags-input';
import {
    Combobox,
    ComboboxAnchor,
    ComboboxEmpty,
    ComboboxGroup,
    ComboboxInput,
    ComboboxItem,
    ComboboxItemIndicator,
    ComboboxList,
    ComboboxTrigger,
} from '@/components/ui/combobox';

const props = defineProps({
    cards: { type: Array, required: true },
    categories: { type: Array, required: true },
    categoryOptions: { type: Array, required: true },
    deleting: { type: Boolean, required: true },
    editing: { type: Boolean, required: true },
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

const emit = defineEmits(['back', 'delete', 'submit']);

const tagSearch = ref('');
const tagInputFocused = ref(false);

const selectedTagValues = computed({
    get: () => props.form.tag_ids.map((tagId) => String(tagId)),
    set: (tagValues) => {
        props.form.tag_ids = tagValues
            .map((tagId) => Number(tagId))
            .filter((tagId) => props.tags.some((tag) => Number(tag.id) === tagId));
    },
});

const selectedCategory = computed({
    get: () => props.categoryOptions.find((category) => Number(category.id) === Number(props.form.category_id)) ?? null,
    set: (category) => {
        props.form.category_id = category?.id ?? '';
    },
});

const selectedPaymentMethod = computed({
    get: () => props.paymentMethods.find((paymentMethod) => paymentMethod.value === props.form.payment_method) ?? null,
    set: (paymentMethod) => {
        props.form.payment_method = paymentMethod?.value ?? 'cash';
    },
});

const selectedCard = computed({
    get: () => props.cards.find((card) => Number(card.id) === Number(props.form.card_id)) ?? null,
    set: (card) => {
        props.form.card_id = card?.id ?? '';
    },
});

const selectedTags = computed(() => props.tags.filter((tag) => selectedTagValues.value.includes(String(tag.id))));

const filteredTags = computed(() => {
    const searchTerm = tagSearch.value.trim().toLocaleLowerCase('es-AR');

    return props.tags.filter((tag) => !selectedTagValues.value.includes(String(tag.id))
        && tag.name.toLocaleLowerCase('es-AR').includes(searchTerm));
});

const selectedTagName = (tagId) => selectedTags.value
    .find((tag) => String(tag.id) === String(tagId))?.name ?? '';

const selectTag = (tag) => {
    selectedTagValues.value = [...selectedTagValues.value, String(tag.id)];
    tagSearch.value = '';
};

const selectFirstFilteredTag = () => {
    if (filteredTags.value[0]) {
        selectTag(filteredTags.value[0]);
    }
};
</script>

<template>
    <section class="grid gap-6">
        <article class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
            <div class="mb-4 flex items-center gap-2">
                <button v-if="editing" type="button" class="rounded-md p-1 text-slate-600 hover:bg-slate-100 hover:text-slate-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-slate-400 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-slate-100 dark:focus-visible:ring-slate-500" aria-label="Volver al listado" title="Volver al listado" @click="emit('back')">
                    <ArrowLeft class="size-4" />
                </button>
                <h2 class="text-base font-semibold">{{ title }}</h2>
            </div>
            <form class="space-y-3" @submit.prevent="emit('submit')">
                <div class="grid gap-3 sm:grid-cols-2">
                    <label v-if="!forcedTransactionType" class="block space-y-1 text-sm">
                        <span class="font-medium">Tipo</span>
                        <select v-model="form.type" class="w-full rounded-md border border-slate-300 bg-white px-3 py-2 dark:border-slate-700 dark:bg-slate-950">
                            <option value="expense">Gasto</option>
                            <option value="income">Ingreso</option>
                        </select>
                    </label>
                    <label class="block space-y-1 text-sm">
                        <span class="font-medium">Monto</span>
                        <input v-model="form.amount" type="number" min="0" step="0.01" required class="w-full rounded-md border border-slate-300 bg-white px-3 py-2 dark:border-slate-700 dark:bg-slate-950">
                    </label>
                </div>

                <label class="block space-y-1 text-sm">
                    <span class="font-medium">Descripción</span>
                    <input v-model="form.description" type="text" required class="w-full rounded-md border border-slate-300 bg-white px-3 py-2 dark:border-slate-700 dark:bg-slate-950">
                </label>

                <div class="space-y-1 text-sm">
                    <span class="font-medium">Categoría</span>
                    <Combobox v-model="selectedCategory" by="id">
                        <ComboboxAnchor as-child>
                            <ComboboxTrigger as-child>
                                <button type="button" class="flex w-full items-center justify-between rounded-md border border-slate-300 bg-white px-3 py-2 text-left dark:border-slate-700 dark:bg-slate-950" aria-label="Seleccionar categoría">
                                    <span class="truncate">{{ selectedCategory?.name ?? 'Sin categoría' }}</span>
                                    <ChevronsUpDown class="ml-2 size-4 shrink-0 text-slate-500 dark:text-slate-400" />
                                </button>
                            </ComboboxTrigger>
                        </ComboboxAnchor>
                        <ComboboxList class="w-[var(--reka-combobox-trigger-width)] max-w-[calc(100vw-2rem)] border-slate-200 bg-white text-slate-900 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100" align="start">
                            <ComboboxInput placeholder="Buscar categoría..." class="border-b border-slate-200 bg-transparent dark:border-slate-700" />
                            <ComboboxEmpty class="px-2 py-3 text-sm text-slate-500 dark:text-slate-400">No se encontraron categorías.</ComboboxEmpty>
                            <ComboboxGroup>
                                <ComboboxItem :value="null">
                                    Sin categoría
                                    <ComboboxItemIndicator class="ml-auto"><Check class="size-4" /></ComboboxItemIndicator>
                                </ComboboxItem>
                                <ComboboxItem v-for="category in categoryOptions" :key="category.id" :value="category">
                                    {{ category.name }}
                                    <ComboboxItemIndicator class="ml-auto"><Check class="size-4" /></ComboboxItemIndicator>
                                </ComboboxItem>
                            </ComboboxGroup>
                        </ComboboxList>
                    </Combobox>
                </div>

                <label class="block space-y-1 text-sm">
                    <span class="font-medium">Tags</span>
                    <div class="relative">
                        <TagsInput v-model="selectedTagValues" class="min-h-10 w-full border-slate-300 bg-white dark:border-slate-700 dark:bg-slate-950">
                            <TagsInputItem v-for="tagId in selectedTagValues" :key="tagId" :value="tagId" class="h-6 bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-200">
                                <TagsInputItemText class="px-2 text-xs">{{ selectedTagName(tagId) }}</TagsInputItemText>
                                <TagsInputItemDelete class="text-slate-500 hover:text-slate-900 dark:text-slate-400 dark:hover:text-slate-100" />
                            </TagsInputItem>
                            <TagsInputInput :value="tagSearch" placeholder="Buscar tags..." @blur="tagInputFocused = false" @focus="tagInputFocused = true" @input="tagSearch = $event.target.value" @keydown.enter.prevent="selectFirstFilteredTag" />
                        </TagsInput>

                        <div v-if="tagInputFocused && filteredTags.length > 0" class="absolute z-10 mt-1 max-h-48 w-full overflow-y-auto rounded-md border border-slate-200 bg-white p-1 shadow-md dark:border-slate-700 dark:bg-slate-900">
                            <button v-for="tag in filteredTags" :key="tag.id" type="button" class="block w-full rounded-sm px-2 py-1.5 text-left text-sm hover:bg-slate-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-slate-400 dark:hover:bg-slate-800 dark:focus-visible:ring-slate-500" @mousedown.prevent="selectTag(tag)">
                                {{ tag.name }}
                            </button>
                        </div>
                    </div>
                    <span class="text-xs text-slate-500 dark:text-slate-400">Escribe para filtrar y presiona Enter o selecciona una etiqueta.</span>
                </label>

                <label class="block space-y-1 text-sm">
                    <span class="font-medium">Fecha</span>
                    <input v-model="form.purchase_date" type="date" required class="w-full rounded-md border border-slate-300 bg-white px-3 py-2 dark:border-slate-700 dark:bg-slate-950">
                </label>

                <div v-if="form.type === 'expense'" class="space-y-1 text-sm">
                    <span class="font-medium">Forma de pago</span>
                    <Combobox v-model="selectedPaymentMethod" by="value">
                        <ComboboxAnchor as-child>
                            <ComboboxTrigger as-child>
                                <button type="button" class="flex w-full items-center justify-between rounded-md border border-slate-300 bg-white px-3 py-2 text-left dark:border-slate-700 dark:bg-slate-950" aria-label="Seleccionar forma de pago">
                                    <span>{{ selectedPaymentMethod?.label ?? 'Selecciona una forma de pago' }}</span>
                                    <ChevronsUpDown class="ml-2 size-4 shrink-0 text-slate-500 dark:text-slate-400" />
                                </button>
                            </ComboboxTrigger>
                        </ComboboxAnchor>
                        <ComboboxList class="w-[var(--reka-combobox-trigger-width)] max-w-[calc(100vw-2rem)] border-slate-200 bg-white text-slate-900 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100" align="start">
                            <ComboboxGroup>
                                <ComboboxItem v-for="paymentMethod in paymentMethods" :key="paymentMethod.value" :value="paymentMethod">
                                    {{ paymentMethod.label }}
                                    <ComboboxItemIndicator class="ml-auto"><Check class="size-4" /></ComboboxItemIndicator>
                                </ComboboxItem>
                            </ComboboxGroup>
                        </ComboboxList>
                    </Combobox>
                </div>

                <div v-if="isCreditPayment" class="space-y-1 text-sm">
                    <span class="font-medium">Tarjeta</span>
                    <Combobox v-model="selectedCard" by="id">
                        <ComboboxAnchor as-child>
                            <ComboboxTrigger as-child>
                                <button type="button" class="flex w-full items-center justify-between rounded-md border border-slate-300 bg-white px-3 py-2 text-left dark:border-slate-700 dark:bg-slate-950" aria-label="Seleccionar tarjeta">
                                    <span class="truncate">{{ selectedCard ? `${selectedCard.name} · ****${selectedCard.last_four_digits}` : 'Selecciona una tarjeta' }}</span>
                                    <ChevronsUpDown class="ml-2 size-4 shrink-0 text-slate-500 dark:text-slate-400" />
                                </button>
                            </ComboboxTrigger>
                        </ComboboxAnchor>
                        <ComboboxList class="w-[var(--reka-combobox-trigger-width)] max-w-[calc(100vw-2rem)] border-slate-200 bg-white text-slate-900 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100" align="start">
                            <ComboboxGroup>
                                <ComboboxItem v-for="card in cards" :key="card.id" :value="card">
                                    {{ card.name }} · ****{{ card.last_four_digits }}
                                    <ComboboxItemIndicator class="ml-auto"><Check class="size-4" /></ComboboxItemIndicator>
                                </ComboboxItem>
                            </ComboboxGroup>
                        </ComboboxList>
                    </Combobox>
                    <p v-if="firstInstallmentPaymentDate" class="text-xs text-slate-500 dark:text-slate-400">
                        La primera cuota se pagará el {{ formatDate(firstInstallmentPaymentDate) }}
                        {{ firstInstallmentPaymentDateIsEstimated ? '(fecha estimada)' : '(fecha real por ciclo cargado)' }}.
                    </p>
                </div>

                <label v-if="isCreditPayment && !editing" class="block space-y-1 text-sm">
                    <span class="font-medium">Cuotas</span>
                    <input v-model.number="form.installments_count" type="number" min="1" max="120" required class="w-full rounded-md border border-slate-300 bg-white px-3 py-2 dark:border-slate-700 dark:bg-slate-950">
                </label>

                <p v-else-if="isCreditPayment" class="text-xs text-slate-500 dark:text-slate-400">La cantidad de cuotas no se puede modificar después de registrar la compra.</p>

                <p v-if="showInstallments" class="text-xs text-slate-500 dark:text-slate-400">La fecha de pago se calcula automáticamente según cierre/vencimiento de la tarjeta.</p>

                <label class="block space-y-1 text-sm">
                    <span class="font-medium">Notas</span>
                    <textarea v-model="form.notes" rows="3" class="w-full rounded-md border border-slate-300 bg-white px-3 py-2 dark:border-slate-700 dark:bg-slate-950"></textarea>
                </label>

                <div class="grid gap-3 sm:grid-cols-2">
                    <button type="submit" :disabled="saving || deleting" class="w-full rounded-md bg-slate-900 px-3 py-2 text-sm font-medium text-white hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-60 dark:bg-slate-100 dark:text-slate-900 dark:hover:bg-slate-200">{{ saving ? 'Guardando...' : (editing ? 'Guardar cambios' : 'Guardar transacción') }}</button>
                    <button v-if="editing" type="button" :disabled="saving || deleting" class="w-full rounded-md border border-red-200 px-3 py-2 text-sm font-medium text-red-700 hover:bg-red-50 disabled:cursor-not-allowed disabled:opacity-60 dark:border-red-900 dark:text-red-400 dark:hover:bg-red-950" @click="emit('delete')">{{ deleting ? 'Eliminando...' : 'Eliminar transacción' }}</button>
                </div>
            </form>
        </article>
    </section>
</template>

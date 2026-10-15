<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { ClipboardCopy, MailPlus, MapPin, RefreshCw } from '@lucide/vue';

const props = defineProps({
    address: { type: String, default: null },
    loading: { type: Boolean, required: true },
    saving: { type: Boolean, required: true },
    providers: { type: Array, required: true },
    categories: { type: Array, required: true },
    places: { type: Array, required: true },
    savingProvider: { type: String, default: null },
});

const emit = defineEmits(['generate', 'rotate', 'save-settings']);
const addressInput = ref(null);
const copyFeedback = ref('');
const selectedCategories = reactive({});
const selectedPlaces = reactive({});
const activePlaceProvider = ref(null);
const expenseCategories = computed(() => props.categories.filter((category) => category.is_active && ['expense', 'both'].includes(category.scope)));

const filteredPlaces = (provider) => {
    const searchTerm = String(selectedPlaces[provider.key] ?? '').trim().toLocaleLowerCase('es-AR');

    return [...new Set(props.places.map((place) => String(place ?? '').trim()).filter(Boolean))]
        .filter((place) => place.toLocaleLowerCase('es-AR').includes(searchTerm));
};

const isPlaceDropdownOpen = (provider) => activePlaceProvider.value === provider.key
    && (filteredPlaces(provider).length > 0 || String(selectedPlaces[provider.key] ?? '').trim());

const placeMatchesSearch = (provider) => filteredPlaces(provider)
    .some((place) => place.toLocaleLowerCase('es-AR') === String(selectedPlaces[provider.key] ?? '').trim().toLocaleLowerCase('es-AR'));

const selectPlace = (provider, place) => {
    selectedPlaces[provider.key] = place;
    activePlaceProvider.value = null;
};

const useTypedPlace = (provider) => {
    selectPlace(provider, String(selectedPlaces[provider.key] ?? '').trim());
};

const closePlaceOnFocusOut = (event) => {
    if (!event.currentTarget.contains(event.relatedTarget)) {
        activePlaceProvider.value = null;
    }
};

watch(() => props.providers, (providers) => {
    for (const provider of providers) {
        selectedCategories[provider.key] = provider.category_id == null ? '' : String(provider.category_id);
        selectedPlaces[provider.key] = provider.place ?? provider.name;
    }
}, { immediate: true });

const saveSettings = (provider) => {
    const categoryId = selectedCategories[provider.key];
    emit('save-settings', provider.key, categoryId === '' ? null : Number(categoryId), selectedPlaces[provider.key]);
};

const settingsChanged = (provider) => selectedCategories[provider.key] !== (provider.category_id == null ? '' : String(provider.category_id))
    || selectedPlaces[provider.key]?.trim() !== provider.place;

watch(() => props.address, () => {
    copyFeedback.value = '';
});

const copyAddress = async () => {
    if (!props.address) {
        return;
    }

    try {
        await navigator.clipboard.writeText(props.address);
        copyFeedback.value = 'Dirección copiada.';
    } catch {
        addressInput.value?.focus();
        addressInput.value?.select();
        copyFeedback.value = 'Seleccioná y copiá la dirección.';
    }
};
</script>

<template>
    <section class="grid gap-6">
        <article class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
            <h2 class="text-base font-semibold">Importar recibos por correo</h2>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Reenviá tus recibos a tu dirección personal de Spendo. No necesitás dar acceso a tu correo.</p>

            <p v-if="loading" class="mt-5 text-sm text-slate-500 dark:text-slate-400">Cargando dirección...</p>

            <div v-else-if="address" class="mt-5 grid gap-3">
                <label for="receipt-import-address" class="text-sm font-medium">Tu dirección de importación</label>
                <div class="flex flex-col gap-2 sm:flex-row">
                    <input id="receipt-import-address" ref="addressInput" :value="address" type="text" readonly autocomplete="off" spellcheck="false" class="min-w-0 flex-1 rounded-md border border-slate-300 bg-slate-50 px-3 py-2 font-mono text-sm text-slate-900 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100" @focus="$event.target.select()">
                    <button type="button" class="inline-flex items-center justify-center gap-2 rounded-md bg-slate-900 px-3 py-2 text-sm font-medium text-white hover:bg-slate-800 dark:bg-slate-100 dark:text-slate-900 dark:hover:bg-slate-200" @click="copyAddress">
                        <ClipboardCopy class="size-4" />
                        Copiar
                    </button>
                </div>
                <p role="status" class="text-sm text-slate-500 dark:text-slate-400">{{ copyFeedback }}</p>
                <p class="text-sm text-slate-500 dark:text-slate-400">Guardá esta dirección como privada: quien la conozca podría enviarte recibos.</p>
                <button type="button" :disabled="saving" class="inline-flex items-center gap-2 justify-self-start rounded-md border border-slate-300 px-3 py-2 text-sm font-medium hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-60 dark:border-slate-700 dark:hover:bg-slate-800" @click="emit('rotate')">
                    <RefreshCw class="size-4" />
                    {{ saving ? 'Regenerando...' : 'Regenerar dirección' }}
                </button>
                <p class="text-xs text-slate-500 dark:text-slate-400">Al regenerarla, la dirección anterior deja de funcionar. Actualizá cualquier regla de reenvío que hayas creado.</p>
            </div>

            <div v-else class="mt-5 grid gap-3">
                <p class="text-sm text-slate-500 dark:text-slate-400">Todavía no tenés una dirección de importación.</p>
                <button type="button" :disabled="saving" class="inline-flex items-center gap-2 justify-self-start rounded-md bg-slate-900 px-3 py-2 text-sm font-medium text-white hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-60 dark:bg-slate-100 dark:text-slate-900 dark:hover:bg-slate-200" @click="emit('generate')">
                    <MailPlus class="size-4" />
                    {{ saving ? 'Generando...' : 'Generar dirección' }}
                </button>
            </div>
        </article>

        <article class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
            <h3 class="text-base font-semibold">Tipos de recibo</h3>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Elegí la categoría y el lugar que usarán los próximos gastos de cada servicio.</p>
            <div v-if="loading" class="mt-4 text-sm text-slate-500 dark:text-slate-400">Cargando servicios...</div>
            <div v-for="provider in providers" :key="provider.key" class="mt-4 rounded-md border border-slate-200 p-4 dark:border-slate-700">
                <h4 class="font-medium">{{ provider.name }}</h4>
                <div class="mt-3 grid gap-3 sm:grid-cols-2">
                    <div class="min-w-0">
                        <label :for="`receipt-category-${provider.key}`" class="mb-1 block text-sm font-medium">Categoría del gasto</label>
                        <select :id="`receipt-category-${provider.key}`" v-model="selectedCategories[provider.key]" class="w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-950" :disabled="savingProvider !== null">
                            <option value="">Sin categoría</option>
                            <option v-for="category in expenseCategories" :key="category.id" :value="String(category.id)">{{ category.name }}</option>
                        </select>
                    </div>
                    <div class="min-w-0">
                        <label :for="`receipt-place-${provider.key}`" class="mb-1 block text-sm font-medium">Lugar del gasto</label>
                        <div class="relative text-sm" @focusout="closePlaceOnFocusOut">
                            <div class="flex w-full items-center gap-2 rounded-md border border-slate-300 bg-white px-3 py-2 dark:border-slate-700 dark:bg-slate-950">
                                <MapPin class="size-4 shrink-0 text-slate-500 dark:text-slate-400" />
                                <input :id="`receipt-place-${provider.key}`" v-model="selectedPlaces[provider.key]" type="text" maxlength="120" placeholder="Buscar o escribir un lugar..." class="min-w-0 flex-1 bg-transparent outline-none" :disabled="savingProvider !== null" @focus="activePlaceProvider = provider.key" @keydown.enter.prevent="filteredPlaces(provider)[0] ? selectPlace(provider, filteredPlaces(provider)[0]) : useTypedPlace(provider)">
                                <button v-if="selectedPlaces[provider.key] || isPlaceDropdownOpen(provider)" type="button" class="text-xs text-slate-500 hover:text-slate-900 dark:text-slate-400 dark:hover:text-slate-100" :disabled="savingProvider !== null" @click="isPlaceDropdownOpen(provider) ? activePlaceProvider = null : selectedPlaces[provider.key] = ''">{{ isPlaceDropdownOpen(provider) ? 'Cerrar' : 'Limpiar' }}</button>
                            </div>
                            <div v-if="isPlaceDropdownOpen(provider)" class="absolute z-10 mt-1 max-h-48 w-full overflow-y-auto rounded-md border border-slate-200 bg-white p-1 shadow-md dark:border-slate-700 dark:bg-slate-900">
                                <button v-for="place in filteredPlaces(provider)" :key="place" type="button" class="block w-full rounded-sm px-2 py-1.5 text-left text-sm hover:bg-slate-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-slate-400 dark:hover:bg-slate-800 dark:focus-visible:ring-slate-500" @mousedown.prevent="selectPlace(provider, place)" @click="selectPlace(provider, place)">
                                    {{ place }}
                                </button>
                                <button v-if="String(selectedPlaces[provider.key] ?? '').trim() && !placeMatchesSearch(provider)" type="button" class="block w-full rounded-sm px-2 py-1.5 text-left text-sm font-medium hover:bg-slate-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-slate-400 dark:hover:bg-slate-800 dark:focus-visible:ring-slate-500" @mousedown.prevent="useTypedPlace(provider)" @click="useTypedPlace(provider)">
                                    Usar “{{ selectedPlaces[provider.key].trim() }}”
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">Si dejás el lugar vacío, se usará {{ provider.name }}.</p>
                <button type="button" class="mt-3 rounded-md bg-slate-900 px-3 py-2 text-sm font-medium text-white hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-60 dark:bg-slate-100 dark:text-slate-900 dark:hover:bg-slate-200" :disabled="savingProvider !== null || !settingsChanged(provider)" @click="saveSettings(provider)">
                    {{ savingProvider === provider.key ? 'Guardando...' : 'Guardar configuración' }}
                </button>
            </div>
            <p v-if="!loading && expenseCategories.length === 0" class="mt-3 text-sm text-slate-500 dark:text-slate-400">Creá una categoría de egresos para asignarla a los recibos.</p>
        </article>

        <article class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
            <h3 class="text-sm font-semibold">Cómo funciona</h3>
            <ol class="mt-3 list-inside list-decimal space-y-2 text-sm text-slate-600 dark:text-slate-300">
                <li>Copiá tu dirección de importación.</li>
                <li>Desde tu correo personal, reenviá un recibo de alguno de los servicios disponibles a esa dirección.</li>
                <li>Si el recibo se reconoce, Spendo crea un egreso. Revisalo en Egresos.</li>
            </ol>
            <p class="mt-4 text-sm text-slate-500 dark:text-slate-400">La fecha del egreso será la del correo reenviado y la forma de pago será Efectivo. Podés editar el egreso si pagaste de otra manera.</p>
        </article>
    </section>
</template>

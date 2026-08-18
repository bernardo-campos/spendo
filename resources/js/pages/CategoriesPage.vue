<script setup>
defineProps({
    categories: { type: Array, required: true },
    form: { type: Object, required: true },
    saving: { type: Boolean, required: true },
    scopeLabel: { type: Function, required: true },
});

const emit = defineEmits(['edit', 'remove', 'reset', 'submit']);
</script>

<template>
    <section class="grid gap-6">
        <article class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
            <h2 class="mb-4 text-base font-semibold">Categorías</h2>
            <form class="grid gap-3 sm:grid-cols-3" @submit.prevent="emit('submit')">
                <input v-model="form.name" type="text" required placeholder="Nombre" class="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-950">
                <select v-model="form.scope" class="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-950">
                    <option value="income">Ingreso</option>
                    <option value="expense">Gasto</option>
                    <option value="both">Ambos</option>
                </select>
                <div class="flex gap-2">
                    <button type="submit" :disabled="saving" class="flex-1 rounded-md bg-slate-900 px-3 py-2 text-sm font-medium text-white hover:bg-slate-800 dark:bg-slate-100 dark:text-slate-900 dark:hover:bg-slate-200">{{ form.id === null ? 'Crear' : 'Actualizar' }}</button>
                    <button type="button" class="rounded-md border border-slate-300 px-3 py-2 text-sm dark:border-slate-700" @click="emit('reset')">Limpiar</button>
                </div>
            </form>
            <ul class="mt-4 space-y-2">
                <li v-for="category in categories" :key="category.id" class="flex items-center justify-between rounded-md border border-slate-200 px-3 py-2 text-sm dark:border-slate-800">
                    <div><p class="font-medium">{{ category.name }}</p><p class="text-xs text-slate-500 dark:text-slate-400">{{ scopeLabel(category.scope) }}</p></div>
                    <div class="flex gap-2"><button type="button" class="rounded-md border border-slate-300 px-2 py-1 text-xs dark:border-slate-700" @click="emit('edit', category)">Editar</button><button type="button" class="rounded-md border border-red-300 px-2 py-1 text-xs text-red-700" @click="emit('remove', category.id)">Eliminar</button></div>
                </li>
            </ul>
        </article>
    </section>
</template>

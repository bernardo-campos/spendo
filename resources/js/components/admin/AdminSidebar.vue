<script setup>
import {
    BarChart3,
    CreditCard,
    FolderTree,
    ReceiptText,
    Tags,
    WalletCards,
    X,
} from '@lucide/vue';

defineProps({
    activeScreen: {
        type: String,
        required: true,
    },
    activePrimaryTab: {
        type: String,
        required: true,
    },
    open: {
        type: Boolean,
        required: true,
    },
});

const emit = defineEmits(['close', 'navigate']);
</script>

<template>
    <aside class="fixed inset-y-0 left-0 z-50 flex w-64 flex-col border-r border-sidebar-border bg-sidebar text-sidebar-foreground transition-transform lg:translate-x-0" :class="open ? 'translate-x-0' : '-translate-x-full'">
        <div class="flex h-16 items-center gap-3 border-b border-sidebar-border px-5">
            <span class="flex size-8 items-center justify-center rounded-lg bg-sidebar-primary text-sm font-bold text-sidebar-primary-foreground">S</span>
            <span class="font-semibold tracking-tight">Spendo</span>
            <button type="button" class="ml-auto rounded-md p-1.5 hover:bg-sidebar-accent lg:hidden" aria-label="Cerrar menú" @click="emit('close')">
                <X class="size-5" />
            </button>
        </div>

        <nav class="flex-1 space-y-1 p-3">
            <p class="px-3 pb-2 pt-3 text-xs font-medium uppercase tracking-wider text-muted-foreground">Finanzas</p>
            <button type="button" class="flex w-full items-center gap-3 rounded-md px-3 py-2 text-sm font-medium transition-colors" :class="activePrimaryTab === 'dashboard' ? 'bg-sidebar-primary text-sidebar-primary-foreground' : 'hover:bg-sidebar-accent'" @click="emit('navigate', 'dashboard')">
                <BarChart3 class="size-4" />
                Resumen
            </button>
            <button type="button" class="flex w-full items-center gap-3 rounded-md px-3 py-2 text-sm font-medium transition-colors" :class="activePrimaryTab === 'income-list' ? 'bg-sidebar-primary text-sidebar-primary-foreground' : 'hover:bg-sidebar-accent'" @click="emit('navigate', 'income-list')">
                <WalletCards class="size-4" />
                Ingresos
            </button>
            <button type="button" class="flex w-full items-center gap-3 rounded-md px-3 py-2 text-sm font-medium transition-colors" :class="activePrimaryTab === 'expense-list' ? 'bg-sidebar-primary text-sidebar-primary-foreground' : 'hover:bg-sidebar-accent'" @click="emit('navigate', 'expense-list')">
                <ReceiptText class="size-4" />
                Egresos
            </button>

            <p class="px-3 pb-2 pt-6 text-xs font-medium uppercase tracking-wider text-muted-foreground">Configuración</p>
            <button type="button" class="flex w-full items-center gap-3 rounded-md px-3 py-2 text-sm font-medium transition-colors" :class="activeScreen === 'cards' ? 'bg-sidebar-primary text-sidebar-primary-foreground' : 'hover:bg-sidebar-accent'" @click="emit('navigate', 'cards')">
                <CreditCard class="size-4" />
                Tarjetas
            </button>
            <button type="button" class="flex w-full items-center gap-3 rounded-md px-3 py-2 text-sm font-medium transition-colors" :class="activeScreen === 'categories' ? 'bg-sidebar-primary text-sidebar-primary-foreground' : 'hover:bg-sidebar-accent'" @click="emit('navigate', 'categories')">
                <FolderTree class="size-4" />
                Categorías
            </button>
            <button type="button" class="flex w-full items-center gap-3 rounded-md px-3 py-2 text-sm font-medium transition-colors" :class="activeScreen === 'tags' ? 'bg-sidebar-primary text-sidebar-primary-foreground' : 'hover:bg-sidebar-accent'" @click="emit('navigate', 'tags')">
                <Tags class="size-4" />
                Etiquetas
            </button>
        </nav>

        <div class="border-t border-sidebar-border p-3 text-xs text-muted-foreground">Tu información financiera, en un solo lugar.</div>
    </aside>
</template>

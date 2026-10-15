<script setup>
import AdminHeader from './AdminHeader.vue';
import AdminSidebar from './AdminSidebar.vue';

defineProps({
    activeScreen: {
        type: String,
        required: true,
    },
    activePrimaryTab: {
        type: String,
        required: true,
    },
    expenseTotals: {
        type: Object,
        required: true,
    },
    formatCurrencyAmount: {
        type: Function,
        required: true,
    },
    incomeTotals: {
        type: Object,
        required: true,
    },
    isDarkMode: {
        type: Boolean,
        required: true,
    },
    selectedPeriod: {
        type: String,
        required: true,
    },
    syncState: {
        type: Object,
        required: true,
    },
    sidebarOpen: {
        type: Boolean,
        required: true,
    },
    transactionsLoading: {
        type: Boolean,
        required: true,
    },
    userInitials: {
        type: String,
        required: true,
    },
    userMenuOpen: {
        type: Boolean,
        required: true,
    },
    userName: {
        type: String,
        required: true,
    },
});

const emit = defineEmits(['navigate', 'retry-sync', 'set-sidebar-open', 'toggle-color-mode', 'toggle-user-menu', 'update:selected-period']);
</script>

<template>
    <div class="min-h-screen bg-background text-foreground">
        <div v-if="sidebarOpen" class="fixed inset-0 z-40 bg-slate-950/50 lg:hidden" @click="emit('set-sidebar-open', false)"></div>
        <AdminSidebar :active-primary-tab="activePrimaryTab" :active-screen="activeScreen" :expense-totals="expenseTotals" :format-currency-amount="formatCurrencyAmount" :income-totals="incomeTotals" :open="sidebarOpen" :transactions-loading="transactionsLoading" @close="emit('set-sidebar-open', false)" @navigate="emit('navigate', $event)" />

        <div class="min-h-screen lg:pl-64">
            <AdminHeader :is-dark-mode="isDarkMode" :selected-period="selectedPeriod" :sync-state="syncState" :user-initials="userInitials" :user-menu-open="userMenuOpen" :user-name="userName" @open-sidebar="emit('set-sidebar-open', true)" @retry-sync="emit('retry-sync')" @toggle-color-mode="emit('toggle-color-mode')" @toggle-user-menu="emit('toggle-user-menu')" @update:selected-period="emit('update:selected-period', $event)">
                <template #user-menu="{ open }">
                    <slot name="user-menu" :open="open" />
                </template>
            </AdminHeader>

            <main class="mx-auto flex w-full max-w-7xl flex-col gap-6 p-4 sm:p-6">
                <div v-if="syncState.failed > 0 || syncState.validationError" role="alert" class="rounded-md border border-red-300 bg-red-50 px-3 py-2 text-sm text-red-700 dark:border-red-900 dark:bg-red-950/40 dark:text-red-200">
                    <p class="font-medium">No se pudieron guardar algunos cambios.</p>
                    <ul class="list-disc pl-5">
                        <li v-if="syncState.validationError">{{ syncState.validationError }}</li>
                        <li v-for="(message, index) in syncState.failedMessages" :key="index">{{ message }}</li>
                    </ul>
                </div>
                <slot />
            </main>
        </div>
    </div>
</template>

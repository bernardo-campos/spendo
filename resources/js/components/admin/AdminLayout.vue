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
    isDarkMode: {
        type: Boolean,
        required: true,
    },
    sidebarOpen: {
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

const emit = defineEmits(['navigate', 'set-sidebar-open', 'toggle-color-mode', 'toggle-user-menu']);
</script>

<template>
    <div class="min-h-screen bg-background text-foreground">
        <div v-if="sidebarOpen" class="fixed inset-0 z-40 bg-slate-950/50 lg:hidden" @click="emit('set-sidebar-open', false)"></div>
        <AdminSidebar :active-primary-tab="activePrimaryTab" :active-screen="activeScreen" :open="sidebarOpen" @close="emit('set-sidebar-open', false)" @navigate="emit('navigate', $event)" />

        <div class="min-h-screen lg:pl-64">
            <AdminHeader :is-dark-mode="isDarkMode" :user-initials="userInitials" :user-menu-open="userMenuOpen" :user-name="userName" @open-sidebar="emit('set-sidebar-open', true)" @toggle-color-mode="emit('toggle-color-mode')" @toggle-user-menu="emit('toggle-user-menu')">
                <template #user-menu="{ open }">
                    <slot name="user-menu" :open="open" />
                </template>
            </AdminHeader>

            <main class="mx-auto flex w-full max-w-7xl flex-col gap-6 p-4 sm:p-6">
                <slot />
            </main>
        </div>
    </div>
</template>

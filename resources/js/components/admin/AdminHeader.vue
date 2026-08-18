<script setup>
import { Menu, Moon, Sun } from '@lucide/vue';

defineProps({
    isDarkMode: {
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

const emit = defineEmits(['open-sidebar', 'toggle-color-mode', 'toggle-user-menu']);
</script>

<template>
    <header class="sticky top-0 z-30 flex h-16 items-center gap-3 border-b border-border bg-background/95 px-4 backdrop-blur lg:px-6">
        <button type="button" class="rounded-md p-2 hover:bg-accent lg:hidden" aria-label="Abrir menú" @click="emit('open-sidebar')">
            <Menu class="size-5" />
        </button>
        <div class="min-w-0 flex-1">
            <p class="text-sm font-semibold">Spendo</p>
            <p class="hidden text-xs text-muted-foreground sm:block">Administrá tus finanzas personales</p>
        </div>
        <button type="button" class="rounded-md p-2 text-muted-foreground hover:bg-accent hover:text-foreground" :aria-label="isDarkMode ? 'Usar tema claro' : 'Usar tema oscuro'" @click="emit('toggle-color-mode')">
            <Sun v-if="isDarkMode" class="size-5" />
            <Moon v-else class="size-5" />
        </button>
        <div class="relative">
            <button type="button" class="flex items-center gap-2 rounded-md p-1.5 hover:bg-accent" @click="emit('toggle-user-menu')">
                <span class="flex size-8 items-center justify-center rounded-full bg-primary text-xs font-semibold text-primary-foreground">{{ userInitials }}</span>
                <span class="hidden max-w-36 truncate text-sm font-medium sm:block">{{ userName }}</span>
            </button>
            <slot name="user-menu" :open="userMenuOpen" />
        </div>
    </header>
</template>

import { onMounted, ref } from 'vue';

export const useColorMode = () => {
    const isDarkMode = ref(false);

    const applyColorMode = (isDark) => {
        isDarkMode.value = isDark;
        document.documentElement.classList.toggle('dark', isDark);
        localStorage.setItem('spendo-color-mode', isDark ? 'dark' : 'light');
    };

    const toggleColorMode = () => {
        applyColorMode(!isDarkMode.value);
    };

    onMounted(() => {
        const storedColorMode = localStorage.getItem('spendo-color-mode');
        const prefersDarkMode = window.matchMedia('(prefers-color-scheme: dark)').matches;

        applyColorMode(storedColorMode ? storedColorMode === 'dark' : prefersDarkMode);
    });

    return {
        applyColorMode,
        isDarkMode,
        toggleColorMode,
    };
};

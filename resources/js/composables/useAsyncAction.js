import { ref } from 'vue';

export const useAsyncAction = () => {
    const loading = ref(false);
    const errorMessage = ref('');
    const successMessage = ref('');

    const runWithLoading = async (handler, fallbackMessage) => {
        loading.value = true;
        errorMessage.value = '';

        try {
            await handler();
        } catch (error) {
            errorMessage.value = error?.response?.data?.message ?? fallbackMessage;
        } finally {
            loading.value = false;
        }
    };

    return {
        errorMessage,
        loading,
        runWithLoading,
        successMessage,
    };
};

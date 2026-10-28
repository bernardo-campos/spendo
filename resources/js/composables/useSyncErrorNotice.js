import { computed, ref, watch } from 'vue';

export const useSyncErrorNotice = (getSyncState) => {
    const dismissed = ref(false);
    const signature = computed(() => {
        const state = getSyncState();
        return JSON.stringify([state.failed, state.failedMessages, state.validationError]);
    });

    watch(signature, () => { dismissed.value = false; }, { flush: 'sync' });
    watch(() => getSyncState().status, (status) => {
        if (status === 'syncing') {
            dismissed.value = false;
        }
    }, { flush: 'sync' });

    const visible = computed(() => ! dismissed.value
        && (getSyncState().failed > 0 || Boolean(getSyncState().validationError)));
    const dismiss = () => { dismissed.value = true; };

    return { visible, dismiss };
};

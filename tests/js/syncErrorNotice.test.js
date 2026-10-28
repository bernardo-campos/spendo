import assert from 'node:assert/strict';
import test from 'node:test';
import { effectScope, reactive } from 'vue';
import { useSyncErrorNotice } from '../../resources/js/composables/useSyncErrorNotice.js';

test('closing the notice keeps failed changes and new errors make it visible again', () => {
    const scope = effectScope();
    try {
        const state = reactive({ status: 'synced', failed: 1, failedMessages: ['Error'], validationError: '' });
        const notice = scope.run(() => useSyncErrorNotice(() => state));
        assert.equal(notice.visible.value, true);
        notice.dismiss();
        assert.equal(notice.visible.value, false);
        assert.equal(state.failed, 1);
        assert.deepEqual([...state.failedMessages], ['Error']);
        state.failedMessages = ['Otro error'];
        assert.equal(notice.visible.value, true);
        notice.dismiss();
        state.status = 'syncing';
        assert.equal(notice.visible.value, true);
        state.failed = 0;
        state.failedMessages = [];
        assert.equal(notice.visible.value, false);
        state.validationError = 'Fecha inválida';
        assert.equal(notice.visible.value, true);
    } finally {
        scope.stop();
    }
});

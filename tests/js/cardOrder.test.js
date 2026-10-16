import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import test from 'node:test';
import { runInNewContext } from 'node:vm';
import { parse } from '@babel/parser';

test('cards keep the same order after a billing cycle refreshes the server copy', async () => {
    const source = readFileSync(resolve('resources/js/composables/useCatalogs.js'), 'utf8');
    const declaration = parse(source, { sourceType: 'module' }).program.body.find((node) => node.type === 'ExportNamedDeclaration'
        && node.declaration?.declarations?.some((item) => item.id.name === 'useCatalogs'));
    const cached = [
        { id: 3, name: 'Visa', billing_cycles: [] },
        { id: 11, name: 'Amex', billing_cycles: [] },
        { id: 2, name: 'Visa', billing_cycles: [] },
    ];
    const server = [
        { id: 2, name: 'Visa', billing_cycles: [{ id: 5 }] },
        { id: 3, name: 'Visa', billing_cycles: [] },
        { id: 11, name: 'Amex', billing_cycles: [] },
    ];
    const context = {
        ref: (value) => ({ value }),
        offlineClient: { get: async (url, options) => {
            assert.equal(url, '/cards');
            return { data: options?.fresh ? server : cached };
        } },
    };

    runInNewContext(`${source.slice(declaration.declaration.start, declaration.declaration.end)}\nglobalThis.subject = useCatalogs;`, context);
    const { cards, loadCards } = context.subject();

    await loadCards();
    assert.deepEqual(Array.from(cards.value, (card) => card.id), [11, 2, 3]);

    await loadCards({ fresh: true });
    assert.deepEqual(Array.from(cards.value, (card) => card.id), [11, 2, 3]);
    assert.equal(cards.value[1].billing_cycles[0].id, 5);
});

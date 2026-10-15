import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import test from 'node:test';
import { runInNewContext } from 'node:vm';
import { parse as parseJavaScript } from '@babel/parser';
import { parse as parseVue } from '@vue/compiler-sfc';

test('a direct receipt import page load initializes its address, settings, and places', async () => {
    const component = readFileSync(resolve('resources/js/components/SpendoApp.vue'), 'utf8');
    const { descriptor } = parseVue(component);
    const script = descriptor.scriptSetup.content;
    const ast = parseJavaScript(script, { sourceType: 'module' });
    const setupStatements = ast.program.body.filter((node) => {
        if (node.type === 'VariableDeclaration') {
            return node.declarations.some((declaration) => ['loadReceiptAddress', 'loadPlaces'].includes(declaration.id.name));
        }

        return node.type === 'ExpressionStatement'
            && node.expression.callee?.name === 'watch'
            && node.expression.arguments[0]?.name === 'activeScreen';
    });
    const loadingReceiptAddress = { value: false };
    const receiptAddress = { value: null };
    const receiptProviders = { value: [] };
    const places = { value: [] };
    const errorMessage = { value: '' };

    runInNewContext(setupStatements.map((node) => script.slice(node.start, node.end)).join('\n'), {
        activeScreen: { value: 'receipt-import' },
        errorMessage,
        loadCategories: async () => {},
        loadingReceiptAddress,
        offlineClient: { get: async () => ({ data: ['Uber', 'Centro'] }) },
        places,
        receiptAddress,
        receiptProviders,
        watch: (source, callback, options) => {
            if (options?.immediate) {
                callback(source.value);
            }
        },
        window: { axios: { get: async (path) => ({
            data: path === '/receipt-import-address'
                ? { address: 'importar@example.test' }
                : { providers: [{ key: 'uber', name: 'Uber' }] },
        }) } },
    });

    await new Promise(setImmediate);

    assert.equal(errorMessage.value, '');
    assert.equal(loadingReceiptAddress.value, false);
    assert.equal(receiptAddress.value, 'importar@example.test');
    assert.deepEqual(receiptProviders.value, [{ key: 'uber', name: 'Uber' }]);
    assert.deepEqual(places.value, ['Uber', 'Centro']);
});

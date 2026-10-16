import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import test from 'node:test';
import { runInNewContext } from 'node:vm';
import { parse } from '@babel/parser';

const source = readFileSync(resolve('resources/js/composables/useCatalogManagement.js'), 'utf8');
const declaration = parse(source, { sourceType: 'module' }).program.body.find((node) => node.type === 'ExportNamedDeclaration'
    && node.declaration?.declarations?.some((item) => item.id.name === 'useCatalogManagement'));
const setup = (mutate) => {
    const context = {
        ref: (value) => ({ value }),
        offlineClient: { mutate },
        toInputDateValue: (value) => String(value).slice(0, 10),
    };
    const script = source.slice(declaration.declaration.start, declaration.declaration.end);
    runInNewContext(`${script}\nglobalThis.subject = useCatalogManagement;`, context);

    const successMessage = { value: '' };
    let cardLoads = 0;
    const catalog = context.subject({
        categories: { value: [] },
        errorMessage: { value: '' },
        form: { value: {} },
        loadCards: async () => { cardLoads += 1; },
        loadCategories: async () => {},
        loadTags: async () => {},
        runWithLoading: async (callback) => callback(),
        successMessage,
        tags: { value: [] },
    });

    return { catalog, successMessage, getCardLoads: () => cardLoads };
};

test('editing only a due day submits the existing cycle to the backend', async () => {
    const requests = [];
    const { catalog, successMessage, getCardLoads } = setup(async (...args) => {
        requests.push(args);
        return { data: { id: 5 } };
    });

    catalog.editBillingCycle(1, { id: 5, closing_date: '2026-10-24', due_date: '2026-11-05' });
    catalog.getBillingCycleForm(1).due_date = '2026-11-06';
    await catalog.submitBillingCycle(1);

    assert.equal(requests.length, 1);
    assert.equal(requests[0][0], 'put');
    assert.equal(requests[0][1], '/cards/1/billing-cycles/5');
    assert.equal(requests[0][2].closing_date, '2026-10-24');
    assert.equal(requests[0][2].due_date, '2026-11-06');
    assert.equal(successMessage.value, 'Ciclo de facturación actualizado correctamente.');
    assert.equal(getCardLoads(), 1);
});

test('backend field errors stay beside the edited cycle and preserve its dates', async () => {
    const { catalog, successMessage } = setup(async () => {
        throw { response: { status: 422, data: { errors: {
            closing_date: ['El mes ya tiene un ciclo.'],
            due_date: ['El vencimiento debe ser posterior al cierre.'],
        } } } };
    });

    catalog.editBillingCycle(1, { id: 5, closing_date: '2026-10-24', due_date: '2026-11-05' });
    catalog.getBillingCycleForm(1).closing_date = '2026-10-25';
    await catalog.submitBillingCycle(1);

    assert.equal(successMessage.value, '');
    assert.equal(catalog.getBillingCycleForm(1).closing_date, '2026-10-25');
    assert.equal(catalog.getBillingCycleForm(1).id, 5);
    assert.equal(catalog.billingCycleErrors.value[1].closing_date[0], 'El mes ya tiene un ciclo.');
    assert.equal(catalog.billingCycleErrors.value[1].due_date[0], 'El vencimiento debe ser posterior al cierre.');

    catalog.clearBillingCycleError(1, 'closing_date');
    assert.equal(catalog.billingCycleErrors.value[1].closing_date.length, 0);
    assert.equal(catalog.billingCycleErrors.value[1].due_date.length, 1);
});

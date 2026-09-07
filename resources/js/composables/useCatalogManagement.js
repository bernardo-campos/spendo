import { ref } from 'vue';

const CATEGORY_SCOPE_LABELS = {
    both: 'Ambos',
    expense: 'Gasto',
    income: 'Ingreso',
};

const normalizeSlug = (value) => value
    .trim()
    .toLowerCase()
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-+|-+$/g, '');

const toInputDateValue = (value) => value ? String(value).slice(0, 10) : '';

export const useCatalogManagement = ({ cards, categories, errorMessage, form, loadCards, loadCategories, loadTags, runWithLoading, successMessage, tags }) => {
    const categoryForm = ref({ id: null, name: '', scope: 'both' });
    const tagForm = ref({ id: null, name: '' });
    const cardForm = ref({
        id: null,
        name: '',
        last_four_digits: '',
        closing_day: '',
        due_day: '',
        is_active: true,
    });
    const billingCycleForms = ref({});
    const savingCategory = ref(false);
    const savingTag = ref(false);
    const savingCard = ref(false);
    const savingBillingCycle = ref(false);

    const categoryScopeLabel = (scope) => CATEGORY_SCOPE_LABELS[scope] ?? scope;

    const resetCategoryForm = () => {
        categoryForm.value = { id: null, name: '', scope: 'both' };
    };

    const resetTagForm = () => {
        tagForm.value = { id: null, name: '' };
    };

    const resetCardForm = () => {
        cardForm.value = {
            id: null,
            name: '',
            last_four_digits: '',
            closing_day: '',
            due_day: '',
            is_active: true,
        };
    };

    const getBillingCycleForm = (cardId) => {
        if (!billingCycleForms.value[cardId]) {
            billingCycleForms.value[cardId] = { id: null, closing_date: '', due_date: '' };
        }

        return billingCycleForms.value[cardId];
    };

    const resetBillingCycleForm = (cardId) => {
        const cycleForm = getBillingCycleForm(cardId);
        cycleForm.id = null;
        cycleForm.closing_date = '';
        cycleForm.due_date = '';
    };

    const editCategory = (category) => {
        categoryForm.value = { id: category.id, name: category.name, scope: category.scope };
    };

    const submitCategory = async () => {
        savingCategory.value = true;
        errorMessage.value = '';
        successMessage.value = '';

        try {
            const payload = { name: categoryForm.value.name, slug: normalizeSlug(categoryForm.value.name), scope: categoryForm.value.scope };

            if (categoryForm.value.id === null) {
                await window.axios.post('/categories', payload);
                successMessage.value = 'Categoría creada correctamente.';
            } else {
                await window.axios.put(`/categories/${categoryForm.value.id}`, payload);
                successMessage.value = 'Categoría actualizada correctamente.';
            }

            resetCategoryForm();
            await runWithLoading(loadCategories, 'No fue posible cargar las categorías.');
        } catch (error) {
            errorMessage.value = error?.response?.data?.message ?? 'No fue posible guardar la categoría.';
        } finally {
            savingCategory.value = false;
        }
    };

    const removeCategory = async (categoryId) => {
        errorMessage.value = '';
        successMessage.value = '';

        try {
            await window.axios.delete(`/categories/${categoryId}`);
            successMessage.value = 'Categoría eliminada correctamente.';

            if (Number(form.value.category_id) === categoryId) {
                form.value.category_id = '';
            }

            await runWithLoading(loadCategories, 'No fue posible cargar las categorías.');
        } catch (error) {
            errorMessage.value = error?.response?.data?.message ?? 'No fue posible eliminar la categoría.';
        }
    };

    const editTag = (tag) => {
        tagForm.value = { id: tag.id, name: tag.name };
    };

    const submitTag = async () => {
        savingTag.value = true;
        errorMessage.value = '';
        successMessage.value = '';

        try {
            const payload = { name: tagForm.value.name, slug: normalizeSlug(tagForm.value.name) };

            if (tagForm.value.id === null) {
                await window.axios.post('/tags', payload);
                successMessage.value = 'Tag creado correctamente.';
            } else {
                await window.axios.put(`/tags/${tagForm.value.id}`, payload);
                successMessage.value = 'Tag actualizado correctamente.';
            }

            resetTagForm();
            await runWithLoading(loadTags, 'No fue posible cargar los tags.');
        } catch (error) {
            errorMessage.value = error?.response?.data?.message ?? 'No fue posible guardar el tag.';
        } finally {
            savingTag.value = false;
        }
    };

    const removeTag = async (tagId) => {
        errorMessage.value = '';
        successMessage.value = '';

        try {
            await window.axios.delete(`/tags/${tagId}`);
            successMessage.value = 'Tag eliminado correctamente.';
            form.value.tag_ids = form.value.tag_ids.filter((value) => Number(value) !== tagId);
            await runWithLoading(loadTags, 'No fue posible cargar los tags.');
        } catch (error) {
            errorMessage.value = error?.response?.data?.message ?? 'No fue posible eliminar el tag.';
        }
    };

    const editCard = (card) => {
        cardForm.value = {
            id: card.id,
            name: card.name,
            last_four_digits: card.last_four_digits,
            closing_day: card.closing_day ?? '',
            due_day: card.due_day ?? '',
            is_active: !!card.is_active,
        };
    };

    const submitCard = async () => {
        savingCard.value = true;
        errorMessage.value = '';
        successMessage.value = '';

        try {
            const payload = {
                name: cardForm.value.name,
                last_four_digits: cardForm.value.last_four_digits,
                closing_day: Number(cardForm.value.closing_day),
                due_day: Number(cardForm.value.due_day),
                is_active: cardForm.value.is_active,
            };

            if (cardForm.value.id === null) {
                await window.axios.post('/cards', payload);
                successMessage.value = 'Tarjeta creada correctamente.';
            } else {
                await window.axios.put(`/cards/${cardForm.value.id}`, payload);
                successMessage.value = 'Tarjeta actualizada correctamente.';
            }

            resetCardForm();
            await runWithLoading(loadCards, 'No fue posible cargar las tarjetas.');
        } catch (error) {
            errorMessage.value = error?.response?.data?.message ?? 'No fue posible guardar la tarjeta.';
        } finally {
            savingCard.value = false;
        }
    };

    const removeCard = async (cardId) => {
        errorMessage.value = '';
        successMessage.value = '';

        try {
            await window.axios.delete(`/cards/${cardId}`);
            successMessage.value = 'Tarjeta eliminada correctamente.';

            if (Number(form.value.card_id) === cardId) {
                form.value.card_id = '';
            }

            await runWithLoading(loadCards, 'No fue posible cargar las tarjetas.');
        } catch (error) {
            errorMessage.value = error?.response?.data?.message ?? 'No fue posible eliminar la tarjeta.';
        }
    };

    const editBillingCycle = (cardId, cycle) => {
        const cycleForm = getBillingCycleForm(cardId);
        cycleForm.id = cycle.id;
        cycleForm.closing_date = toInputDateValue(cycle.closing_date);
        cycleForm.due_date = toInputDateValue(cycle.due_date);
    };

    const submitBillingCycle = async (cardId) => {
        savingBillingCycle.value = true;
        errorMessage.value = '';
        successMessage.value = '';

        try {
            const cycleForm = getBillingCycleForm(cardId);
            const payload = { closing_date: cycleForm.closing_date, due_date: cycleForm.due_date };

            if (cycleForm.id === null) {
                await window.axios.post(`/cards/${cardId}/billing-cycles`, payload);
                successMessage.value = 'Ciclo de facturación creado correctamente.';
            } else {
                await window.axios.put(`/cards/${cardId}/billing-cycles/${cycleForm.id}`, payload);
                successMessage.value = 'Ciclo de facturación actualizado correctamente.';
            }

            resetBillingCycleForm(cardId);
            await runWithLoading(loadCards, 'No fue posible cargar las tarjetas.');
        } catch (error) {
            errorMessage.value = error?.response?.data?.message ?? 'No fue posible guardar el ciclo de facturación.';
        } finally {
            savingBillingCycle.value = false;
        }
    };

    return {
        billingCycleForms,
        cardForm,
        categoryForm,
        categoryScopeLabel,
        editBillingCycle,
        editCard,
        editCategory,
        editTag,
        getBillingCycleForm,
        removeCard,
        removeCategory,
        removeTag,
        resetBillingCycleForm,
        resetCardForm,
        resetCategoryForm,
        resetTagForm,
        savingBillingCycle,
        savingCard,
        savingCategory,
        savingTag,
        submitBillingCycle,
        submitCard,
        submitCategory,
        submitTag,
        tagForm,
    };
};

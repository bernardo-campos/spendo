import { ref } from 'vue';
import { offlineClient } from '../services/offlineClient';

export const useCatalogs = () => {
    const categories = ref([]);
    const tags = ref([]);
    const cards = ref([]);

    const categorySortKey = (category) => String(category.name ?? '')
        .replace(/[^\p{L}\p{N}\s]/gu, '')
        .trim();

    const sortCategories = (categoryList) => [...categoryList].sort((left, right) => categorySortKey(left)
        .localeCompare(categorySortKey(right), 'es-AR', { sensitivity: 'base' }));

    const sortCards = (cardList) => [...cardList].sort((left, right) => String(left.name ?? '')
        .localeCompare(String(right.name ?? ''), 'es-AR', { sensitivity: 'base' })
        || String(left.id).localeCompare(String(right.id), 'es-AR', { numeric: true }));

    const loadCategories = async () => {
        const response = await offlineClient.get('/categories');
        categories.value = sortCategories(response.data);
    };

    const loadTags = async () => {
        const response = await offlineClient.get('/tags');
        tags.value = response.data;
    };

    const loadCards = async ({ fresh = false } = {}) => {
        const response = await offlineClient.get('/cards', { fresh });
        cards.value = sortCards(response.data);
    };

    const ensureTransactionFormData = async () => {
        await Promise.all([
            categories.value.length === 0 ? loadCategories() : Promise.resolve(),
            tags.value.length === 0 ? loadTags() : Promise.resolve(),
            cards.value.length === 0 ? loadCards() : Promise.resolve(),
        ]);
    };

    return {
        cards,
        categories,
        ensureTransactionFormData,
        loadCards,
        loadCategories,
        loadTags,
        tags,
    };
};

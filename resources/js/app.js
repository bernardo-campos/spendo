import './bootstrap';
import { createApp } from 'vue';
import SpendoApp from './components/SpendoApp.vue';
import { offlineClient } from './services/offlineClient';

const rootElement = document.getElementById('spendo-app');

if (rootElement) {
    void offlineClient.initialize(rootElement.dataset.userId);
    createApp(SpendoApp).mount(rootElement);

    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.register('/service-worker.js').then(async (registration) => {
            await navigator.serviceWorker.ready;
            const assets = performance.getEntriesByType('resource')
                .map((entry) => entry.name)
                .filter((asset) => asset.startsWith(window.location.origin));
            (registration.active ?? registration.waiting)?.postMessage({ type: 'CACHE_PRIVATE_SHELL', assets });
        });
    }

    window.addEventListener('online', () => {
        void offlineClient.sync();
    });
}

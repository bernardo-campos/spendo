import './bootstrap';
import { createApp } from 'vue';
import SpendoApp from './components/SpendoApp.vue';
import { offlineClient } from './services/offlineClient';

const rootElement = document.getElementById('spendo-app');

if (rootElement) {
    const boot = async () => {
        const changedUser = await offlineClient.initialize(rootElement.dataset.userId);

        if ('serviceWorker' in navigator) {
            const registration = await navigator.serviceWorker.register('/service-worker.js');
            await navigator.serviceWorker.ready;

            if (changedUser) {
                (registration.active ?? registration.waiting)?.postMessage({ type: 'CLEAR_PRIVATE_OFFLINE_DATA' });
            }
        }

        createApp(SpendoApp).mount(rootElement);

        if ('serviceWorker' in navigator) {
            const registration = await navigator.serviceWorker.ready;
            const assets = performance.getEntriesByType('resource')
                .map((entry) => entry.name)
                .filter((asset) => asset.startsWith(window.location.origin));
            (registration.active ?? registration.waiting)?.postMessage({ type: 'CACHE_PRIVATE_SHELL', assets });
        }

        window.addEventListener('online', () => {
            void offlineClient.sync();
        });
    };

    void boot();
}

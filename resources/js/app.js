import './bootstrap';
import { createApp } from 'vue';
import SpendoApp from './components/SpendoApp.vue';

const rootElement = document.getElementById('spendo-app');

if (rootElement) {
	createApp(SpendoApp).mount(rootElement);
}

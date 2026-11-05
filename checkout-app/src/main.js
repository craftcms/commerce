import {createApp} from 'vue';
import App from './App.vue';
import {config} from './api.js';
import './style.css';

// The checkout's appearance settings
const root = document.documentElement;
root.dataset.scheme = config.theme.colorScheme;
root.dataset.corners = config.theme.cornerStyle;
if (config.theme.brandColor) root.style.setProperty('--checkout-brand', config.theme.brandColor);

createApp(App).mount('#checkout');

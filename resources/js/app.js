/**
 * First we will load all of this project's JavaScript dependencies which
 * includes Vue and other libraries.
 */

import './bootstrap';
import { createApp } from 'vue';

/**
 * Next, we will create a fresh Vue application instance and register the
 * components, then attach it to the page.
 */

const app = createApp({});

import ExampleComponent from './components/ExampleComponent.vue';
app.component('example-component', ExampleComponent);

/**
 * Register every component in this directory automatically ("basename" →
 * tag), e.g. ./components/ExampleComponent.vue → <example-component>.
 */

// Object.entries(import.meta.glob('./**/*.vue', { eager: true })).forEach(([path, definition]) => {
//     app.component(path.split('/').pop().replace(/\.\w+$/, ''), definition.default);
// });

app.mount('#app');

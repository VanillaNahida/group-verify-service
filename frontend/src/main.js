import ArcoVue from '@arco-design/web-vue';
import '@arco-design/web-vue/dist/arco.css';
import App from './App.vue';
import { ViteSSG } from 'vite-ssg/single-page';

export const createApp = ViteSSG(App, ({ app }) => {
  app.use(ArcoVue);
});


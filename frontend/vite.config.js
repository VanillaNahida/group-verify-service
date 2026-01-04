import { defineConfig } from 'vite';
import vue from '@vitejs/plugin-vue';

export default defineConfig({
  plugins: [vue()],
  base: '/static/verify/',
  build: {
    outDir: '../backend/public/static/verify',
    emptyOutDir: true
  }
});


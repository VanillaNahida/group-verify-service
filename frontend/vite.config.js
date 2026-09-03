import { defineConfig } from 'vite';
import vue from '@vitejs/plugin-vue';
import path from 'path';

export default defineConfig({
  plugins: [vue()],
  resolve: {
    alias: {
      '@': path.resolve(__dirname, './src')
    }
  },
  base: '/static/verify/',
  build: {
    outDir: '../backend/public/static/verify',
    emptyOutDir: true,
    rollupOptions: {
      output: {
        // Keep the Vue/Arco dependency graph in one module. Splitting these
        // packages independently can create an ES module initialization cycle.
        manualChunks(id) {
          return String(id || '').includes('node_modules') ? 'vendor' : undefined;
        }
      }
    }
  }
});

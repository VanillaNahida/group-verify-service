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
        manualChunks(id) {
          const s = String(id || '');
          if (!s.includes('node_modules')) return;
          if (s.includes('@arco-design')) return 'arco';
          if (s.includes(`${path.sep}vue${path.sep}`) || s.includes(`${path.sep}@vue${path.sep}`)) return 'vue';
          return 'vendor';
        }
      }
    }
  }
});


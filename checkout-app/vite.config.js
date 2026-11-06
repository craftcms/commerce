import {defineConfig} from 'vite';
import vue from '@vitejs/plugin-vue';
import tailwindcss from '@tailwindcss/vite';

// Builds the hosted checkout into the CheckoutAsset bundle’s dist folder, with stable file names.
export default defineConfig({
  plugins: [vue(), tailwindcss()],
  build: {
    outDir: '../src/web/assets/checkout/dist',
    emptyOutDir: true,
    cssCodeSplit: false,
    rollupOptions: {
      input: 'src/main.js',
      output: {
        entryFileNames: 'checkout.js',
        chunkFileNames: 'checkout-[name].js',
        assetFileNames: (asset) =>
          asset.names?.some((n) => n.endsWith('.css'))
            ? 'checkout.css'
            : 'assets/[name]-[hash][extname]',
      },
    },
  },
});

import {defineConfig} from 'vite';
import vue from '@vitejs/plugin-vue';
import laravel from 'laravel-vite-plugin';

/**
 * Commerce's own Vite build for the new CP `Form` system's Commerce-owned
 * Node/Control components (entry: `resources/js/cp.ts`) — a pnpm workspace
 * root, matching `cms`'s own top-level `vite.config.js` placement and
 * `resources/js/cp.ts` entry-point convention exactly. The legacy Vue 2/
 * webpack `commerceui` bundle lives in `./legacy`, a separate workspace
 * *member* package (own `package.json`, own `node_modules`) — the two can't
 * share a `vue` dependency (Vue 3 here, Vue 2 there), the same reason `cms`
 * itself keeps `packages/craftcms-legacy` separate from its own root build.
 *
 * Output lands at `public/build` — `CraftCms\Cms\Plugin\Concerns\
 * HasFrontendAssets`'s default `publicDirectory`/`buildDirectory`
 * ('public'/'build'), resolved relative to the Commerce plugin's own base
 * path (`dirname(Plugin::getBasePath())` = this repo root) — is what
 * actually reads this at runtime, so this path is load-bearing, not just a
 * convention to match `cms`'s own layout.
 */
export default defineConfig({
  plugins: [
    laravel({
      input: ['resources/js/cp.ts'],
      publicDirectory: 'public',
      buildDirectory: 'build',
      refresh: false,
    }),
    vue(),
  ],
  build: {
    outDir: 'public/build',
    emptyOutDir: true,
  },
});

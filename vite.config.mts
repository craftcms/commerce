import {defineConfig, type Plugin} from 'vite';
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
/**
 * Specifiers the CP publishes through its import map (see `cms`'s
 * `Cp::sharedModules()`). Left out of the build so Commerce's components share
 * the CP's own instances rather than bundling second copies.
 *
 * Each maps to its named exports, or `null` for a package the dev server can
 * import itself to find them — `@craftcms/cms/elements` only exists in the
 * browser, through the import map.
 */
const cpSharedModuleExports: Record<string, string[] | null> = {
  vue: null,
  '@craftcms/cms/elements': [
    'ElementIndexPage',
    'ElementEditor',
    'CpButtonLink',
    'appendIndexQuery',
  ],
};
const cpSharedModules = Object.keys(cpSharedModuleExports);

/**
 * `build.rollupOptions.external` only applies to builds, and the dev server
 * rewrites even an external bare `vue` import to its own `/@id/` URL, which the
 * import map can't match. So in dev each shared module resolves to a stand-in
 * that loads it through a specifier Vite can't rewrite, leaving the browser to
 * resolve it via the import map, and re-exports its named exports.
 *
 * The CP normally serves a production build of Vue, which has no HMR runtime,
 * so component updates fall back to a full reload unless `cms`'s own dev server
 * is providing a development build.
 */
function externalCpSharedModules(): Plugin {
  const prefix = '\0commerce-cp-shared:';

  return {
    name: 'commerce:external-cp-shared-modules',
    apply: 'serve',
    enforce: 'pre',
    resolveId(id) {
      return cpSharedModules.includes(id) ? prefix + id : null;
    },
    async load(id) {
      if (!id.startsWith(prefix)) {
        return null;
      }

      const specifier = id.slice(prefix.length);
      const names =
        cpSharedModuleExports[specifier] ??
        Object.keys(await import(specifier)).filter(
          (name) => name !== 'default' && /^[A-Za-z_$][\w$]*$/.test(name)
        );

      return [
        `const specifier = ${JSON.stringify(specifier)};`,
        'const shared = await import(/* @vite-ignore */ specifier);',
        `export const {${names.join(', ')}} = shared;`,
        ...(specifier === 'vue'
          ? [
              'globalThis.__VUE_HMR_RUNTIME__ ??= {',
              '  createRecord: () => true,',
              '  rerender: () => location.reload(),',
              '  reload: () => location.reload(),',
              '};',
            ]
          : []),
      ].join('\n');
    },
  };
}

export default defineConfig({
  plugins: [
    externalCpSharedModules(),
    laravel({
      input: ['resources/js/cp.ts'],
      publicDirectory: 'public',
      buildDirectory: 'build',
      refresh: false,
    }),
    vue({
      template: {
        compilerOptions: {
          isCustomElement: (tag) => tag.includes('-'),
        },
      },
    }),
  ],
  build: {
    outDir: 'public/build',
    emptyOutDir: true,
    rollupOptions: {
      external: cpSharedModules,
    },
  },
  optimizeDeps: {
    exclude: cpSharedModules,
  },
});

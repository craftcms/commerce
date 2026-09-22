import UsageCounterNode from './modules/forms/UsageCounterNode.vue';

/**
 * Commerce's own contribution to the shared CP `Form` system's component
 * registry. `window.Cp` is a deliberate public bridge `cms` exposes for
 * exactly this (see `cms/resources/js/bootstrap/cp.ts`'s `window.Cp = Cp;`).
 *
 * On genuine Inertia pages (`app.blade.php`), `cms`'s own bundle is
 * guaranteed to run first — `Cp::viteScripts()` renders before
 * `Plugins::getAssetsHtml()` (which is what enqueues this script, via
 * `CraftCms\Commerce\Plugin::$vite` + `HasFrontendAssets`). That ordering
 * guarantee does *not* hold on legacy-compat-rendered pages (still-Yii2-
 * template screens like Orders, not yet converted to the new Form system) —
 * their own asset registration can enqueue this script *before* `cms`'s,
 * confirmed live (script tag order swapped on `commerce/orders`, throwing
 * `Cannot read properties of undefined (reading '$components')`). Rather
 * than depend on load order at all, wait for `window.Cp` to actually exist.
 *
 * Component names are prefixed `commerce:` (rather than reusing `cms`'s own
 * `craft:` prefix) so a Commerce-contributed type can never collide with a
 * future `cms`-native one of a similar name.
 */
function registerComponents(): void {
  window.Cp.$components.register('commerce:usage-counter', UsageCounterNode);
}

if (window.Cp) {
  registerComponents();
} else {
  const id = setInterval(() => {
    if (window.Cp) {
      clearInterval(id);
      registerComponents();
    }
  }, 10);
}

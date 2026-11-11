/**
 * Ambient types for the slice of `cms`'s public `window.Cp` global
 * this bundle actually uses. Hand-authored, not imported from `cms` —
 * `cms`'s own TS source (`resources/js/**`) is private to that package, not a
 * published dependency this project can resolve. Keep this in sync by hand if
 * the real shape changes; see `cms/resources/js/bootstrap/cp.ts` (`Cp`) for the
 * source of truth.
 */

interface CpComponentRegistration {
  // A plain Vue component.
  [key: string]: unknown;
}

interface CpComponentRegistry {
  // Takes a plain component, or (matching `cms`'s own registry) an async
  // loader resolving to one.
  register(
    name: string,
    component: CpComponentRegistration | (() => Promise<unknown>)
  ): void;
}

interface InertiaPageRegistry {
  // A page component, or a loader resolving to one (or its module).
  register(name: string, componentOrLoader: unknown): void;
}

interface InertiaRouter {
  reload(options?: {only?: string[]}): void;
  visit(
    url: string,
    options?: {preserveState?: boolean; preserveScroll?: boolean}
  ): void;
}

declare global {
  interface Window {
    Cp: {
      $components: CpComponentRegistry;
      $inertia: InertiaPageRegistry;
      $router: InertiaRouter;
    };
  }
}

export {};

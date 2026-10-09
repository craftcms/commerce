/**
 * Ambient types for the slice of `cms`'s public `window.Cp`/`window.Craft`
 * globals this bundle actually uses. Hand-authored, not imported from `cms` —
 * `cms`'s own TS source (`resources/js/**`) is private to that package, not a
 * published dependency this project can resolve. Keep this in sync by hand if
 * the real shape changes; see `cms/resources/js/bootstrap/cp.ts` (`Cp`) and
 * `cms/resources/js/common/types/globals.d.ts` (`Craft`) for the source of
 * truth.
 */

interface CpComponentRegistration {
  // A plain Vue component, or (matching `cms`'s own registry) an async loader
  // returning one — only the plain-component shape is used here.
  [key: string]: unknown;
}

interface CpComponentRegistry {
  register(name: string, component: CpComponentRegistration): void;
}

interface InertiaPageRegistry {
  // A page component, or a loader resolving to one (or its module).
  register(name: string, componentOrLoader: unknown): void;
}

interface InertiaRouter {
  reload(options?: {only?: string[]}): void;
}

declare global {
  interface Window {
    Cp: {
      $components: CpComponentRegistry;
      $inertia: InertiaPageRegistry;
      $router: InertiaRouter;
    };
    Craft: {
      t(category: string, message: string, params?: Record<string, unknown>): string;
      sendActionRequest<T = unknown>(
        method: string,
        action: string,
        options?: {data?: Record<string, unknown>}
      ): Promise<{data: T}>;
      csrfTokenValue?: string;
    };
  }
}

export {};

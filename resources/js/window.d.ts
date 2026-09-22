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

interface InertiaRouter {
  reload(options?: {only?: string[]}): void;
}

/**
 * Only the slice of axios's real `AxiosInstance` this bundle actually calls.
 * `@craftcms/ui`'s own `actionClient` (unavailable here — see file header)
 * layers a CSRF-refresh-on-419/403 retry interceptor on top of a plain axios
 * instance; this bundle deliberately doesn't replicate that (it'd mean
 * porting its `Csrf` token-refresh service too, real but genuinely deep
 * plumbing for what these are — infrequent, admin-only actions) and instead
 * relies on the baseline `X-CSRF-TOKEN` header `cp.ts`'s own `start()`
 * already sets as an axios default. Worth revisiting if this ever proves to
 * be a real problem in practice.
 */
interface MinimalAxiosInstance {
  post<T = unknown>(
    url: string,
    data?: Record<string, unknown>
  ): Promise<{data: T}>;
}

declare global {
  interface Window {
    Cp: {
      $components: CpComponentRegistry;
      $router: InertiaRouter;
      $axios: MinimalAxiosInstance;
    };
    Craft: {
      t(category: string, message: string, params?: Record<string, unknown>): string;
      csrfTokenValue?: string;
    };
  }
}

export {};

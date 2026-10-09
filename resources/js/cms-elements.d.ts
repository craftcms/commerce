/**
 * Ambient types for `@craftcms/cms/elements`, the element index `cms` shares
 * with plugin bundles through the import map (see `cms`'s `Cp::sharedModules()`
 * and `resources/js/elements.ts`). Hand-authored for the same reason as
 * `window.d.ts`; keep in sync with `cms`'s `ElementIndexPage.vue`,
 * `ElementEditor.vue`, `CpButtonLink.vue`, `ActionMenu.vue`, `common/types` and
 * `useElementIndexVisits.ts`, `useAppLayout.ts`, `useCustomizeSources.ts`.
 */
declare module '@craftcms/cms/elements' {
  import type {ComputedRef, DefineComponent} from 'vue';

  export type IndexQueryValue =
    | string
    | number
    | boolean
    | null
    | undefined
    | IndexQueryValue[]
    | IndexQueryParams;

  export interface IndexQueryParams {
    [key: string]: IndexQueryValue;
  }

  export interface ElementIndexRoute {
    url(query?: IndexQueryParams): string;
  }

  export function appendIndexQuery(
    url: string,
    query: IndexQueryParams
  ): string;

  export const ElementIndexPage: DefineComponent<{
    route: ElementIndexRoute;
    sourceHref?: string;
    customizableSources?: boolean;
  }>;

  export const ElementEditor: DefineComponent<{
    saveData?: () => Record<string, unknown>;
  }>;

  export interface ActionItemLink {
    type: 'link';
    href: string;
    label: string;
    icon?: string;
    external?: boolean;
    selected?: boolean;
  }

  export interface ActionItemGroup {
    type: 'group';
    heading?: string;
    items: ActionItemLink[];
  }

  export interface ActionItemButton {
    type?: 'button';
    label: string;
    icon?: string;
    onClick?: (event: Event) => void;
  }

  export type ActionItem = ActionItemLink | ActionItemGroup | ActionItemButton;

  export interface NavItem {
    label: string | null;
    href: string | null;
    selected: boolean;
    group: boolean;
    subnav: NavItem[] | false;
  }

  export interface UseAppLayoutOptions {
    subnav?: NavItem[];
    subnavActions?: ActionItem[];
  }

  export function useAppLayout(
    options: UseAppLayoutOptions | (() => UseAppLayoutOptions)
  ): void;

  export interface CustomizeSourcesTarget {
    elementType?: string | null;
    page?: string | null;
    sourceKey?: string | null;
  }

  export function useCustomizeSources(
    target: () => CustomizeSourcesTarget
  ): ComputedRef<ActionItemButton[]>;

  export const ActionMenu: DefineComponent<{
    actions: ActionItem[];
    icon?: string;
    label?: string | null;
    buttonVariant?: string;
    searchable?: boolean;
    flush?: boolean | string;
  }>;

  export const CpButtonLink: DefineComponent<{
    href: string;
    variant?: string;
    size?: 'zero' | 'small' | 'medium' | 'large';
    icon?: string;
    target?: string;
    inertia?: boolean;
  }>;
}

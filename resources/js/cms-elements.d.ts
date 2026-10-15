/**
 * Ambient types for `@craftcms/cp`, the element index `cms` shares
 * with plugin bundles through the import map (see `cms`'s `Cp::sharedModules()`
 * and `resources/js/cp-module.ts`). Hand-authored for the same reason as
 * `window.d.ts`; keep in sync with `cms`'s `ElementIndexPage.vue`,
 * `ElementEditor.vue`, `CpButtonLink.vue`, `ActionMenu.vue`, `common/types` and
 * `useElementIndexVisits.ts`, `useAppLayout.ts`, `useCustomizeSources.ts`,
 * `open-ui-modal.ts`.
 */
declare module '@craftcms/cp' {
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
    filterParams?: IndexQueryParams;
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
    variant?: string;
    onClick?: (event: Event) => void;
  }

  export interface UiModalOptions {
    modalUrl: string;
    actionUrl: string;
    params?: Record<string, unknown>;
    title?: string;
    submitLabel?: string;
    width?: string;
    onSubmitted?: (data: Record<string, unknown>) => void;
  }

  export function openUiModal(options: UiModalOptions): Promise<void>;

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
    route?: ElementIndexRoute | null;
    sourceHref?: string | null;
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

  export const AdminTable: DefineComponent<{
    table: CraftTable<any>;
    readOnly?: boolean;
    emptyMessage?: string | null;
  }>;

  export const ElementActionMenu: DefineComponent<{
    items: unknown[];
    flush?: boolean | string;
  }>;

  export const MetadataDetails: DefineComponent<{
    html: string | null | undefined;
  }>;

  export interface CraftTable<TData> {
    readonly __data?: TData;
  }

  export interface CraftCellContext<TData> {
    row: {original: TData};
  }

  export interface CraftColumnHelper<TData> {
    display(column: {
      id: string;
      header?: string;
      cell: (context: CraftCellContext<TData>) => unknown;
    }): unknown;
  }

  export function createCraftColumnHelper<TData>(): CraftColumnHelper<TData>;

  export function useCraftTable<TData>(options: {
    data: TData[];
    columns: unknown[];
  }): CraftTable<TData>;
}

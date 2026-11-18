/**
 * Ambient types for `@craftcms/cp`, the module `cms` shares with plugin
 * bundles through the import map (see `cms`'s `Cp::sharedModules()` and
 * `resources/js/cp-module.ts`). Hand-authored: `cms` doesn't publish its types
 * as a package this project can resolve. Keep in sync with `cp-module.ts` and
 * the `cms` sources it re-exports from. The table types are loose: the
 * TanStack generics behind them aren't worth mirroring by hand.
 */
declare module '@craftcms/cp' {
  import type {Component, ComputedRef, DefineComponent} from 'vue';

  type Registration =
    | Component
    | (() => Promise<Component | {default: Component}>);

  export function registerComponent(
    name: string,
    component: Registration
  ): void;

  export function registerPage(name: string, page: Registration): void;

  export type UiChangeKind = 'discrete' | 'typing';

  export interface UiChange {
    kind: UiChangeKind;
    path: string[];
    scope?: string[];
    refreshable?: boolean;
  }

  export interface UiNodePayload<
    Props extends object = Record<string, unknown>
  > {
    uid: string;
    props: Props;
  }

  export interface UiControlPayload<
    Props extends object = Record<string, unknown>
  > {
    path: string[];
    props: Props;
  }

  export function showCopyTextPrompt(config: {
    label?: string;
    value: string;
  }): HTMLElement;

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

  export interface ActionResponse<T> {
    data: T;
    headers: Record<string, string>;
  }

  export const actionClient: {
    request<T = unknown>(config: {
      method: string;
      url: string;
      data?: unknown;
      params?: Record<string, unknown>;
      responseType?: 'json' | 'blob' | 'text';
    }): Promise<ActionResponse<T>>;
  };

  export function showNotice(message: string): unknown;

  export function showError(message?: string | null): unknown;

  export function t(
    message: string,
    params?: Record<string, unknown>,
    category?: string
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

  export interface PaginationData {
    total: number;
    per_page: number;
    current_page: number;
    last_page: number;
    next_page_url: string | null;
    prev_page_url: string | null;
    from: number | null;
    to: number | null;
  }

  export interface CraftTableInertiaOptions {
    url: string;
    pagination?: () => PaginationData;
    dataProp?: string;
  }

  export function useCraftTable<TData>(options: {
    readonly data: TData[];
    columns: unknown[];
    getRowId?: (row: TData) => string;
    inertia?: CraftTableInertiaOptions;
  }): unknown;

  export interface CraftCellContext<TData, TValue> {
    getValue(): TValue;
    row: {original: TData};
  }

  export interface CraftColumnHelper<TData> {
    columns(columns: unknown[]): unknown[];
    accessor<TKey extends keyof TData>(
      key: TKey,
      config: {
        header: string;
        meta?: {trackSize?: string};
        cell?: (cell: CraftCellContext<TData, TData[TKey]>) => unknown;
      }
    ): unknown;
  }

  export function createCraftColumnHelper<TData>(): CraftColumnHelper<TData>;

  export const AdminTable: DefineComponent<{
    table: unknown;
    title?: string;
    from?: number | null;
    to?: number | null;
    total?: number;
    emptyMessage?: string | null;
    enableAdjustPageSize?: boolean;
  }>;

  export const SearchForm: DefineComponent<{
    action: string;
    modelValue?: string | null;
    options?: Record<string, unknown>;
  }>;
}

<script setup lang="ts">
  import {
    appendIndexQuery,
    CpButtonLink,
    ElementIndexPage,
    useAppLayout,
    useCustomizeSources,
    type ElementIndexRoute,
    type IndexQueryParams,
    type NavItem,
  } from '@craftcms/cp';
  import {computed, onMounted, onUnmounted, ref} from 'vue';
  import DownloadOrderPdfPopover, {
    type OrderPdfOption,
  } from '../../modules/orders/DownloadOrderPdfPopover.vue';
  import OrderDateRangeMenu, {
    type DateRangeValue,
  } from '../../modules/orders/OrderDateRangeMenu.vue';
  import UpdateOrderStatusDialog, {
    type OrderStatusOption,
  } from '../../modules/orders/UpdateOrderStatusDialog.vue';
  import {
    COPY_LOAD_CART_URL_EVENT,
    DOWNLOAD_ORDER_PDF_EVENT,
    promptLoadCartUrl,
    UPDATE_ORDER_STATUS_EVENT,
    type OrderActionEventDetail,
  } from '../../modules/orders/order-actions';

  const props = defineProps<{
    indexUrl: string;
    orderStatusHandle: string;
    newOrderUrl: string | null;
    newOrderLabel: string;
    dateRange: DateRangeValue;
    dateRangeOptions: Array<{value: string; label: string}>;
    orderStatuses: OrderStatusOption[] | null;
    pdfs: OrderPdfOption[];
    labels: Record<string, string>;
    sourceNavItems?: NavItem[];
    elementType?: string | null;
    page?: string | null;
    source?: {key?: string | null} | null;
  }>();

  // `indexUrl` carries the `site` param `Url::cpUrl()` adds, which the index
  // query is merged over.
  function indexUrlWith(path: string, query = {}): string {
    const [base = '', search = ''] = props.indexUrl.split('?');

    return appendIndexQuery(`${base}${path}`, {
      ...Object.fromEntries(new URLSearchParams(search)),
      ...query,
    });
  }

  const route: ElementIndexRoute = {
    url: (query = {}) =>
      indexUrlWith(
        props.orderStatusHandle ? `/${props.orderStatusHandle}` : '',
        query
      ),
  };

  // Switching sources leaves the status handle segment behind, so the new
  // source isn't overridden by the one in the URL. The switch adds `site`
  // itself.
  const sourceHref = props.indexUrl.split('?')[0];

  const subnavActions = useCustomizeSources(() => ({
    elementType: props.elementType,
    page: props.page,
    sourceKey: props.source?.key,
    route,
    sourceHref,
  }));

  useAppLayout(() => ({
    subnav: props.sourceNavItems ?? [],
    subnavActions: subnavActions.value,
  }));

  const dateRangeParams = (value: DateRangeValue) => ({
    dateRange: value.type ?? undefined,
    dateFrom: value.type === 'range' ? (value.from ?? undefined) : undefined,
    dateTo: value.type === 'range' ? (value.to ?? undefined) : undefined,
  });

  const filterParams = computed<IndexQueryParams>(() =>
    dateRangeParams(props.dateRange)
  );

  function changeDateRange(value: DateRangeValue): void {
    const url = new URL(window.location.href);

    for (const [name, param] of Object.entries(dateRangeParams(value))) {
      if (param) {
        url.searchParams.set(name, param);
      } else {
        url.searchParams.delete(name);
      }
    }

    url.searchParams.delete('page');
    window.Cp.$router.visit(url.href, {
      preserveState: true,
      preserveScroll: true,
    });
  }

  const defaultStatusId = computed(() => {
    const handle = props.source?.key?.startsWith('orderStatus:')
      ? props.source.key.slice('orderStatus:'.length)
      : null;

    return (
      props.orderStatuses?.find((status) => status.handle === handle)?.id ??
      null
    );
  });

  const statusDetail = ref<OrderActionEventDetail | null>(null);
  const pdfDetail = ref<OrderActionEventDetail | null>(null);
  const pdfAnchor = ref<Element | null>(null);

  // A fresh visit rather than a partial reload, so the selection (held by the
  // index, out of the page's reach) goes with the orders that moved away.
  function refreshIndex(): void {
    window.Cp.$router.visit(window.location.href, {
      preserveState: false,
      preserveScroll: true,
    });
  }

  function onUpdateOrderStatus(event: Event): void {
    statusDetail.value = (event as CustomEvent<OrderActionEventDetail>).detail;
  }

  function onCopyLoadCartUrl(event: Event): void {
    void promptLoadCartUrl(
      (event as CustomEvent<OrderActionEventDetail>).detail,
      props.labels.copyTheUrl
    );
  }

  // The action's menu item is hidden as the menu closes, so the popover hangs
  // off the menu's button. It opens a frame later, once the click that chose
  // the action has finished, or that click would count as one outside it.
  function onDownloadOrderPdf(event: Event): void {
    const detail = (event as CustomEvent<OrderActionEventDetail>).detail;
    const anchor =
      detail.trigger
        ?.closest('craft-action-menu')
        ?.querySelector(':scope > [slot="invoker"]') ??
      detail.trigger ??
      null;

    pdfDetail.value = detail;
    requestAnimationFrame(() => {
      pdfAnchor.value = anchor;
    });
  }

  function closePdfPopover(): void {
    pdfDetail.value = null;
    pdfAnchor.value = null;
  }

  const listeners: Array<[string, (event: Event) => void]> = [
    [UPDATE_ORDER_STATUS_EVENT, onUpdateOrderStatus],
    [COPY_LOAD_CART_URL_EVENT, onCopyLoadCartUrl],
    [DOWNLOAD_ORDER_PDF_EVENT, onDownloadOrderPdf],
  ];

  onMounted(() => {
    listeners.forEach(([name, listener]) =>
      window.addEventListener(name, listener)
    );
  });

  onUnmounted(() => {
    listeners.forEach(([name, listener]) =>
      window.removeEventListener(name, listener)
    );
  });
</script>

<template>
  <ElementIndexPage
    :route="route"
    :source-href="sourceHref"
    :filter-params="filterParams"
  >
    <template #toolbar-actions>
      <OrderDateRangeMenu
        :value="dateRange"
        :options="dateRangeOptions"
        :labels="labels"
        @change="changeDateRange"
      />
      <!-- A full page load: the create action redirects to the edit screen,
           which isn't an Inertia page. -->
      <CpButtonLink
        v-if="newOrderUrl"
        :href="newOrderUrl"
        :inertia="false"
        icon="plus"
        >{{ newOrderLabel }}</CpButtonLink
      >
    </template>
  </ElementIndexPage>

  <UpdateOrderStatusDialog
    v-if="orderStatuses"
    :detail="statusDetail"
    :order-statuses="orderStatuses"
    :default-status-id="defaultStatusId"
    :labels="labels"
    @close="statusDetail = null"
    @updated="refreshIndex"
  />

  <DownloadOrderPdfPopover
    :detail="pdfDetail"
    :anchor="pdfAnchor"
    :pdfs="pdfs"
    :labels="labels"
    @close="closePdfPopover"
  />
</template>

<script setup lang="ts">
  import {
    computed,
    getCurrentInstance,
    onBeforeUnmount,
    onMounted,
    ref,
    watch,
  } from 'vue';

  defineOptions({inheritAttrs: false});

  type TableRow = Record<string, unknown>;

  type CatalogPricesData = {
    heading: string;
    rows: TableRow[];
    jobsRunning: boolean;
  };

  type CatalogPricesProps = {
    heading: string;
    jobsRunning: boolean;
    refreshAction: string;
    refreshParams: Record<string, unknown>;
    createUrl: string | null;
    pricesChanged: boolean;
    labels: Record<'pricesChanged' | 'jobsRunning', string>;
  };

  // See UsageCounterNode.vue for why this isn't imported from `cms`.
  type UiNodePayload<Props> = {
    uid: string;
    props: Props;
    children?: UiNodePayload<Record<string, unknown>>[] | null;
  };

  const POLL_INTERVAL = 3000;
  const MAX_POLLS = 40;
  const SLIDEOUT_WIDTH = '85vw';

  const props = defineProps<{
    node: UiNodePayload<CatalogPricesProps>;
  }>();

  // The rows are a cms `Table` node, rendered with cms's own admin table.
  const adminTable =
    getCurrentInstance()!.appContext.components['craft:admin-table'];
  const tableNode = computed(() => props.node.children![0]);

  const data = ref<CatalogPricesData>(initialData());
  let pollTimer: ReturnType<typeof setTimeout> | undefined;
  let polls = 0;

  const table = computed(() => ({
    ...tableNode.value,
    props: {...tableNode.value.props, rows: data.value.rows},
  }));

  function initialData(): CatalogPricesData {
    return {
      heading: props.node.props.heading,
      rows: tableNode.value.props.rows as TableRow[],
      jobsRunning: props.node.props.jobsRunning,
    };
  }

  function schedulePoll(): void {
    clearTimeout(pollTimer);

    if (!data.value.jobsRunning || polls >= MAX_POLLS) {
      return;
    }

    polls++;
    pollTimer = setTimeout(() => void refresh(), POLL_INTERVAL);
  }

  async function refresh(): Promise<void> {
    const response = await window.Craft.sendActionRequest<CatalogPricesData>(
      'POST',
      props.node.props.refreshAction,
      {data: props.node.props.refreshParams}
    );
    data.value = response.data;
    schedulePoll();
  }

  /**
   * Rule links and the create action are Inertia links in the admin table, so
   * their mouse events are taken over here to open them in a wide slideout
   * rather than navigating away.
   */
  function slideoutUrl(event: MouseEvent): string | null {
    const link = (event.target as Element).closest('[href]');

    if (
      !link ||
      event.altKey ||
      event.ctrlKey ||
      event.metaKey ||
      event.shiftKey ||
      event.button !== 0
    ) {
      return null;
    }

    const href = new URL(link.getAttribute('href')!, window.location.href);
    const createUrl = props.node.props.createUrl;

    if (
      createUrl !== null &&
      href.pathname === new URL(createUrl, window.location.href).pathname
    ) {
      return createUrl;
    }

    return link.hasAttribute('data-catalog-pricing-rule') ? href.href : null;
  }

  function onMouse(event: MouseEvent): void {
    const url = slideoutUrl(event);

    if (url === null) {
      return;
    }

    event.preventDefault();
    event.stopPropagation();

    if (event.type !== 'click') {
      return;
    }

    void window.Craft.openSlideout(url, {
      width: SLIDEOUT_WIDTH,
      onSaved: (result) => {
        if (result.draft) {
          return;
        }

        polls = 0;
        void refresh();
      },
    });
  }

  watch(
    () => props.node,
    () => {
      data.value = initialData();
      polls = 0;
      schedulePoll();
    }
  );

  onMounted(schedulePoll);
  onBeforeUnmount(() => clearTimeout(pollTimer));
</script>

<template>
  <craft-disclosure
    class="catalog-prices width-100"
    :label="data.heading"
    :data-ui-node="node.uid"
  >
    <div slot="content" class="catalog-prices__content">
      <p v-if="node.props.pricesChanged" class="catalog-prices__notice">
        <craft-icon name="circle-info"></craft-icon>
        {{ node.props.labels.pricesChanged }}
      </p>
      <p v-if="data.jobsRunning" class="catalog-prices__notice" role="status">
        <craft-spinner size="small"></craft-spinner>
        {{ node.props.labels.jobsRunning }}
      </p>
      <div
        class="catalog-prices__table"
        @click.capture="onMouse"
        @mousedown.capture="onMouse"
        @mouseup.capture="onMouse"
      >
        <component :is="adminTable" :node="table" />
      </div>
    </div>
  </craft-disclosure>
</template>

<style scoped>
  .catalog-prices__content {
    display: grid;
    gap: var(--c-spacing-sm);
    padding-block-start: var(--c-spacing-sm);
  }

  .catalog-prices__table {
    min-width: 0;
  }

  .catalog-prices__table :deep(td) {
    white-space: normal;
  }

  .catalog-prices__notice {
    display: flex;
    gap: var(--c-spacing-xs);
    align-items: center;
    margin: 0;
    color: var(--c-text-quiet);
  }

  :deep(.catalog-prices__rule) {
    display: flex;
    flex-wrap: wrap;
    gap: var(--c-spacing-xs);
    align-items: center;
  }

  :deep(.catalog-prices__rule craft-badge) {
    white-space: nowrap;
  }

  :deep(.catalog-prices__secondary) {
    font-size: var(--c-text-sm);
    color: var(--c-text-quiet);
  }
</style>

<script setup lang="ts">
    import {
        ActionMenu,
        AdminTable,
        ElementActionMenu,
        MetadataDetails,
        createCraftColumnHelper,
        openUiModal,
        useCraftTable,
        type ActionItem,
    } from '@craftcms/cp';
    import {
        computed,
        h,
        nextTick,
        onBeforeUnmount,
        onMounted,
        ref,
        resolveComponent,
        watch,
        type CSSProperties,
    } from 'vue';
    import ComboboxFieldPopover, {
        type ComboboxFieldOption,
    } from '../../modules/orders/ComboboxFieldPopover.vue';
    import TextFieldPopover from '../../modules/orders/TextFieldPopover.vue';

    interface Adjustment {
        type: string;
        name: string;
        description: string | null;
        amount: string;
        included: boolean;
    }

    interface LineItemRow {
        uid: string | null;
        description: string;
        sku: string;
        url: string | null;
        qty: number;
        fulfilledQty: number;
        price: string;
        salePrice: string | null;
        subtotal: string;
        total: string;
        statusHtml: string | null;
        note: string;
        privateNote: string;
        options: Array<{key: string; value: string}>;
        adjustments: Adjustment[];
        change: 'added' | 'changed' | 'removed' | null;
    }

    interface Session {
        mine: boolean;
        userName: string | null;
        since: string;
        stale: boolean;
        error: string | null;
        operations: Array<{index: number; label: string}>;
        originalTotal: string | null;
        newTotal: string | null;
        balance: string | null;
    }

    interface Notice {
        id: number | null;
        noticeType: string;
        type: string;
        attribute: string;
        message: string;
        admin: boolean;
    }

    interface TransactionRow {
        id: number | null;
        level: number;
        type: string;
        status: string;
        amount: string;
        paymentAmount: string;
        gateway: string;
        date: string;
        reference: string | null;
        message: string | null;
        note: string;
    }

    interface HistoryRow {
        newStatusHtml: string | null;
        prevStatusHtml: string | null;
        userHtml: string;
        date: string;
        messageHtml: string | null;
    }

    interface UiNode {
        uid?: string | null;
        component: string;
        props: Record<string, unknown>;
    }

    interface UiPayload {
        nodes: UiNode[];
        [key: string]: unknown;
    }

    const props = defineProps<{
        orderId: number;
        isCompleted: boolean;
        canEdit: boolean;
        editUrls: Record<string, string>;
        session: Session | null;
        changes: string[];
        changedFields: string[];
        dateUpdated: string | null;
        statusHtml: string[];
        actionMenu: unknown[];
        notices: Notice[];
        lineItems: LineItemRow[];
        adjustments: Adjustment[];
        totals: Array<{label: string; amount: string}>;
        totalPrice: string;
        payments: {paid: string; outstanding: string} | null;
        couponCode: string | null;
        shippingMethodName: string | null;
        shippingMethodHandle: string;
        orderSiteId: string;
        orderSiteOptions: ComboboxFieldOption[];
        reference: string | null;
        shippingMethodOptions: ComboboxFieldOption[];
        customer: {chipHtml: string; email: string | null} | null;
        email: string | null;
        shippingAddressHtml: string | null;
        billingAddressHtml: string | null;
        details: Array<{
            key: string;
            label: string;
            value: string;
            form: string | null;
        }>;
        metadataHtml: string;
        fieldsUi: UiPayload | null;
        purchasableTypes: Array<{type: string; label: string}>;
        transactions: TransactionRow[];
        histories: HistoryRow[];
        labels: Record<string, string>;
    }>();

    const LayoutSlot = resolveComponent('craft:layout-slot');
    const CpContainer = resolveComponent('craft:cp-container');
    const UiRenderer = resolveComponent('craft:ui');

    interface PageTab {
        id: string;
        label: string;
        fieldsUi?: UiPayload;
    }

    const editable = computed(
        () => props.canEdit && (!props.isCompleted || !!props.session?.mine)
    );
    const canStartEditing = computed(
        () => props.canEdit && props.isCompleted && !props.session
    );
    const reviewing = ref(false);
    const indicatorStyle: CSSProperties = {
        position: 'absolute',
        insetBlockStart: '0',
        insetInlineStart: 'calc(var(--c-spacing-lg, 1rem) * -1)',
        width: '2px',
        height: '100%',
        cursor: 'help',
        borderRadius: '1px',
        backgroundColor: 'var(--blue-600, #2563eb)',
        boxShadow: '0 0 5px hsl(221deg 83% 53% / 15%)',
    };
    const changeKindStyles: Record<string, CSSProperties> = {
        added: {
            backgroundColor: 'var(--green-600, #16a34a)',
            boxShadow: '0 0 5px hsl(142deg 76% 36% / 20%)',
        },
        removed: {
            backgroundColor: 'var(--red-600, #dc2626)',
            boxShadow: '0 0 5px hsl(0deg 72% 51% / 20%)',
        },
    };
    const modifiedFields = computed(() =>
        props.changedFields.map((handle) => `fields.${handle}`)
    );
    const detailsEl = ref<HTMLElement | null>(null);
    const changeBars = ref<Array<{top: number; height: number; kind: string}>>(
        []
    );
    let layoutObserver: ResizeObserver | undefined;

    function measureChangeBars(): void {
        const container = detailsEl.value;

        if (!container) {
            changeBars.value = [];

            return;
        }

        const top = container.getBoundingClientRect().top;
        const rows = Array.from(
            container.querySelectorAll('[data-line-items] tbody tr')
        );
        const changedRows = props.lineItems.flatMap((item, index) => {
            const row = rows[index] as HTMLElement | undefined;

            if (!row) {
                return [];
            }

            if (item.change) {
                row.dataset.change = item.change;
            } else {
                delete row.dataset.change;
            }

            return item.change ? [{element: row, kind: item.change}] : [];
        });
        const changedBlocks = Array.from(
            container.querySelectorAll('[data-changed]')
        ).map((element) => ({element, kind: 'changed'}));

        changeBars.value = [...changedRows, ...changedBlocks].map(
            ({element, kind}) => {
                const rect = element.getBoundingClientRect();

                return {top: rect.top - top, height: rect.height, kind};
            }
        );
    }

    onMounted(() => {
        const observer = new ResizeObserver(measureChangeBars);
        layoutObserver = observer;
        watch(
            detailsEl,
            (container) => {
                observer.disconnect();

                if (container) {
                    observer.observe(container);
                }

                void nextTick(measureChangeBars);
            },
            {immediate: true}
        );
    });
    onBeforeUnmount(() => layoutObserver?.disconnect());
    watch(
        () => [props.lineItems, props.session, props.changes],
        () => void nextTick(measureChangeBars)
    );

    const totalChanged = computed(
        () =>
            !!props.session?.mine &&
            props.session.originalTotal !== props.session.newTotal
    );
    const changeCount = computed(() => {
        const count = props.session?.operations.length ?? 0;

        return count === 1
            ? props.labels.oneChange
            : props.labels.changeCount.replace('{count}', String(count));
    });

    interface Snapshot {
        title: string;
        sections: Array<{label: string; json: string}>;
    }

    const snapshot = ref<Snapshot | null>(null);

    async function showSnapshot(item: LineItemRow): Promise<void> {
        try {
            const {data} = await window.Craft.sendActionRequest<Snapshot>(
                'GET',
                props.editUrls.snapshot,
                {params: {orderId: props.orderId, lineItemUid: item.uid}}
            );
            snapshot.value = data;
        } catch (error: any) {
            window.Craft.cp?.displayError(error?.response?.data?.message);
        }
    }

    function editParams(): Record<string, string> {
        return {
            orderId: String(props.orderId),
            dateUpdated: props.dateUpdated ?? '',
        };
    }

    function openForm(
        form: string,
        params: Record<string, string> = {},
        width?: string
    ): void {
        void openUiModal({
            modalUrl: props.editUrls.form,
            actionUrl: props.editUrls.form,
            params: {...editParams(), form, ...params},
            width,
            onSubmitted: () => window.Cp.$router.reload(),
        });
    }

    function openSlideout(
        url: string,
        params: Record<string, string> = {},
        opener?: EventTarget | null
    ): void {
        const query = new URLSearchParams({...editParams(), ...params});

        void (window.Craft as any).openSlideout(`${url}?${query}`, {
            opener,
            onSaved: () => window.Cp.$router.reload(),
        });
    }

    function openFieldsSlideout(event: Event): void {
        openSlideout(props.editUrls.fields, {}, event.currentTarget);
    }

    function showError(error: any): void {
        window.Craft.cp?.displayError(error?.response?.data?.message);
    }

    async function post(
        url: string,
        data: Record<string, unknown>
    ): Promise<boolean> {
        try {
            const {data: response} = await window.Craft.sendActionRequest<{
                message?: string;
            }>('POST', url, {data: {orderId: props.orderId, ...data}});

            if (response.message) {
                window.Craft.cp?.displayNotice(response.message);
            }

            return true;
        } catch (error: any) {
            showError(error);

            return false;
        }
    }

    async function apply(
        operations: Array<Record<string, unknown>>
    ): Promise<void> {
        if (props.isCompleted) {
            for (const operation of operations) {
                if (!(await post(props.editUrls.add, {operation}))) {
                    break;
                }
            }
        } else {
            await post(props.editUrls.apply, {...editParams(), operations});
        }

        window.Cp.$router.reload();
    }

    function selectShippingMethod(value: string): void {
        const handle = value === '-none' ? '' : value;
        const option = props.shippingMethodOptions.find(
            (option) => option.value === value
        );

        void apply([
            {
                type: 'setShippingMethod',
                shippingMethodHandle: handle || null,
                shippingMethodName: handle ? option?.data?.name : null,
            },
        ]);
    }

    async function startEditing(): Promise<void> {
        await post(props.editUrls.start, {});
        window.Cp.$router.reload();
    }

    async function takeOver(): Promise<void> {
        await post(props.editUrls.takeOver, {});
        window.Cp.$router.reload();
    }

    async function discardEdit(): Promise<void> {
        if (window.confirm(props.labels.confirmDiscard)) {
            reviewing.value = false;
            await post(props.editUrls.discard, {});
            window.Cp.$router.reload();
        }
    }

    async function removeChange(index: number): Promise<void> {
        await post(props.editUrls.remove, {index});
        window.Cp.$router.reload();
    }

    async function commitEdit(): Promise<void> {
        await post(props.editUrls.commit, {});
        reviewing.value = false;
        window.Cp.$router.reload();
    }

    function addressActions(addressType: 'shipping' | 'billing'): ActionItem[] {
        const hasAddress =
            (addressType === 'shipping'
                ? props.shippingAddressHtml
                : props.billingAddressHtml) !== null;

        return [
            {
                label: props.labels.edit,
                icon: 'pencil',
                onClick: () =>
                    openSlideout(props.editUrls.address, {addressType}),
            },
            ...(props.customer
                ? [
                      {
                          label: props.labels.chooseFromAddressBook,
                          onClick: () =>
                              openForm('setAddress', {
                                  addressType,
                                  mode: 'book',
                              }),
                      },
                  ]
                : []),
            ...(hasAddress
                ? [
                      {
                          label:
                              addressType === 'shipping'
                                  ? props.labels.copyToBilling
                                  : props.labels.copyToShipping,
                          onClick: () =>
                              void apply([
                                  {type: 'copyAddress', from: addressType},
                              ]),
                      },
                      {
                          label: props.labels.removeAddress,
                          icon: 'trash',
                          variant: 'danger',
                          onClick: () =>
                              void apply([
                                  {type: 'removeAddress', addressType},
                              ]),
                      },
                  ]
                : []),
        ];
    }

    function removeLineItem(item: LineItemRow): void {
        if (
            window.confirm(
                props.labels.confirmRemoveLineItem.replace(
                    '{item}',
                    item.description
                )
            )
        ) {
            void apply([{type: 'removeLineItem', lineItemUid: item.uid}]);
        }
    }

    function dismissNotice(notice: Notice): void {
        void apply([
            {
                type: 'clearNotices',
                noticeType: notice.noticeType,
                noticeIds: [notice.id],
            },
        ]);
    }

    function lineItemActions(item: LineItemRow): ActionItem[] {
        const params = {lineItemUid: item.uid ?? ''};

        const editActions: ActionItem[] = editable.value
            ? [
                  {
                      label: props.labels.edit,
                      icon: 'pencil',
                      onClick: () => openForm('updateLineItem', params, 'xl'),
                  },
                  {
                      label: props.labels.setStatus,
                      onClick: () => openForm('setLineItemStatus', params),
                  },
                  {
                      label: props.labels.notes,
                      onClick: () => openForm('setLineItemNotes', params),
                  },
                  {
                      label: props.labels.options,
                      onClick: () => openForm('setLineItemOptions', params),
                  },
              ]
            : [];

        return [
            ...editActions,
            ...(item.change === 'added'
                ? []
                : [
                      {
                          label: props.labels.viewSnapshot,
                          onClick: () => void showSnapshot(item),
                      },
                  ]),
            ...(item.url
                ? [
                      {
                          type: 'link' as const,
                          href: item.url,
                          label: props.labels.viewPurchasable,
                      },
                  ]
                : []),
            ...(editable.value
                ? [
                      {
                          label: props.labels.remove,
                          icon: 'trash',
                          variant: 'danger',
                          onClick: () => removeLineItem(item),
                      },
                  ]
                : []),
        ];
    }

    const addActions = computed<ActionItem[]>(() => [
        ...props.purchasableTypes.map((purchasableType) => ({
            label:
                props.purchasableTypes.length === 1
                    ? props.labels.addLineItem
                    : props.labels.addType.replace(
                          '{type}',
                          purchasableType.label
                      ),
            onClick: () =>
                openForm('addLineItem', {
                    purchasableType: purchasableType.type,
                }),
        })),
        {
            label: props.labels.addCustomLineItem,
            onClick: () => openForm('addCustomLineItem'),
        },
    ]);

    const tabs = computed<PageTab[]>(() => [
        {id: 'details', label: props.labels.details},
        ...(props.fieldsUi?.nodes ?? [])
            .filter((node) => node.component === 'craft:tab')
            .map((node, index) => ({
                id: `fields-${node.uid ?? index}`,
                label: String(node.props.label ?? ''),
                fieldsUi: {...props.fieldsUi!, nodes: [node]},
            })),
        {id: 'transactions', label: props.labels.transactions},
        {id: 'history', label: props.labels.history},
    ]);

    const activeTab = ref('details');

    function onTabShow(event: Event): void {
        const index = (event.target as {selectedIndex?: number} | null)
            ?.selectedIndex;
        const tab = index === undefined ? undefined : tabs.value[index];

        if (tab) {
            activeTab.value = tab.id;
        }
    }

    const lineItemColumns = createCraftColumnHelper<LineItemRow>();
    const lineItemsTable = useCraftTable<LineItemRow>({
        get data() {
            return props.lineItems;
        },
        get columns() {
            return [
                lineItemColumns.display({
                    id: 'item',
                    header: props.labels.item,
                    cell: ({row}) => {
                        const item = row.original;

                        const name = item.url
                            ? h(
                                  'a',
                                  {href: item.url, class: 'font-bold'},
                                  item.description
                              )
                            : h('span', {class: 'font-bold'}, item.description);

                        return h(
                            'div',
                            {
                                class:
                                    item.change === 'removed'
                                        ? 'line-through'
                                        : '',
                            },
                            [
                                item.change === 'added' ||
                                item.change === 'removed'
                                    ? h(
                                          'span',
                                          {class: 'sr-only'},
                                          item.change === 'added'
                                              ? props.labels.changeAdded
                                              : props.labels.changeRemoved
                                      )
                                    : null,
                                name,
                                item.sku
                                    ? h(
                                          'div',
                                          {class: 'text-sm'},
                                          h('code', item.sku)
                                      )
                                    : null,
                                ...item.options.map((option) =>
                                    h(
                                        'div',
                                        {class: 'text-sm'},
                                        `${option.key}: ${option.value}`
                                    )
                                ),
                                item.note
                                    ? h(
                                          'div',
                                          {class: 'text-sm'},
                                          `${props.labels.note}: ${item.note}`
                                      )
                                    : null,
                                item.privateNote
                                    ? h(
                                          'div',
                                          {class: 'text-sm'},
                                          `${props.labels.privateNote}: ${item.privateNote}`
                                      )
                                    : null,
                                ...item.adjustments.map((adjustment) =>
                                    h(
                                        'div',
                                        {class: 'text-sm'},
                                        `${adjustment.type}: ${adjustment.name} ${adjustment.amount}`
                                    )
                                ),
                            ]
                        );
                    },
                }),
                ...(props.lineItems.some((item) => item.statusHtml)
                    ? [
                          lineItemColumns.display({
                              id: 'status',
                              header: props.labels.status,
                              cell: ({row}) =>
                                  row.original.statusHtml
                                      ? h('span', {
                                            innerHTML: row.original.statusHtml,
                                        })
                                      : null,
                          }),
                      ]
                    : []),
                lineItemColumns.display({
                    id: 'qty',
                    header: props.labels.qty,
                    cell: ({row}) =>
                        h('div', [
                            h('div', String(row.original.qty)),
                            row.original.fulfilledQty
                                ? h(
                                      'div',
                                      {class: 'text-sm'},
                                      props.labels.fulfilled.replace(
                                          '{count}',
                                          String(row.original.fulfilledQty)
                                      )
                                  )
                                : null,
                        ]),
                }),
                lineItemColumns.display({
                    id: 'price',
                    header: props.labels.price,
                    cell: ({row}) =>
                        row.original.salePrice
                            ? h('div', [
                                  h('del', row.original.price),
                                  h('div', row.original.salePrice),
                              ])
                            : row.original.price,
                }),
                lineItemColumns.display({
                    id: 'total',
                    header: props.labels.total,
                    cell: ({row}) => row.original.total,
                }),
                lineItemColumns.display({
                    id: 'actions',
                    cell: ({row}) =>
                        row.original.change === 'removed'
                            ? null
                            : h(ActionMenu, {
                                  actions: lineItemActions(row.original),
                                  label: props.labels.actions,
                              }),
                }),
            ];
        },
    });

    const transactionColumns = createCraftColumnHelper<TransactionRow>();
    const transactionsTable = useCraftTable<TransactionRow>({
        get data() {
            return props.transactions;
        },
        get columns() {
            return [
                transactionColumns.display({
                    id: 'type',
                    header: props.labels.type,
                    cell: ({row}) =>
                        h(
                            'span',
                            {class: row.original.level ? 'ps-4' : 'font-bold'},
                            row.original.type
                        ),
                }),
                transactionColumns.display({
                    id: 'status',
                    header: props.labels.status,
                    cell: ({row}) => row.original.status,
                }),
                transactionColumns.display({
                    id: 'amount',
                    header: props.labels.amount,
                    cell: ({row}) => row.original.amount,
                }),
                transactionColumns.display({
                    id: 'paymentAmount',
                    header: props.labels.paymentAmount,
                    cell: ({row}) => row.original.paymentAmount,
                }),
                transactionColumns.display({
                    id: 'gateway',
                    header: props.labels.gateway,
                    cell: ({row}) => row.original.gateway,
                }),
                transactionColumns.display({
                    id: 'reference',
                    header: props.labels.reference,
                    cell: ({row}) =>
                        row.original.reference
                            ? h('code', row.original.reference)
                            : null,
                }),
                transactionColumns.display({
                    id: 'date',
                    header: props.labels.date,
                    cell: ({row}) => row.original.date,
                }),
            ];
        },
    });

    const historyColumns = createCraftColumnHelper<HistoryRow>();
    const historyTable = useCraftTable<HistoryRow>({
        get data() {
            return props.histories;
        },
        get columns() {
            return [
                historyColumns.display({
                    id: 'status',
                    header: props.labels.status,
                    cell: ({row}) =>
                        h('div', [
                            h('span', {
                                innerHTML: row.original.newStatusHtml ?? '',
                            }),
                            row.original.messageHtml
                                ? h('div', {
                                      class: 'text-sm',
                                      innerHTML: row.original.messageHtml,
                                  })
                                : null,
                        ]),
                }),
                historyColumns.display({
                    id: 'previousStatus',
                    header: props.labels.previousStatus,
                    cell: ({row}) =>
                        h('span', {
                            innerHTML: row.original.prevStatusHtml ?? '',
                        }),
                }),
                historyColumns.display({
                    id: 'updatedBy',
                    header: props.labels.updatedBy,
                    cell: ({row}) =>
                        h('span', {innerHTML: row.original.userHtml}),
                }),
                historyColumns.display({
                    id: 'date',
                    header: props.labels.date,
                    cell: ({row}) => row.original.date,
                }),
            ];
        },
    });
</script>

<template>
    <component
        :is="LayoutSlot"
        v-if="statusHtml.length"
        name="content-toolbar-meta"
    >
        <span v-for="(html, index) in statusHtml" :key="index" v-html="html" />
    </component>

    <component
        :is="LayoutSlot"
        v-if="actionMenu.length"
        name="content-toolbar-actions"
    >
        <ElementActionMenu :items="actionMenu" />
    </component>

    <component
        :is="LayoutSlot"
        v-if="notices.length || session"
        name="content-notices"
    >
        <craft-callout
            v-if="session && session.mine"
            variant="accent"
            icon="edit"
            appearance="fill"
            rounded="none"
        >
            {{ labels.editingNotice }}
            <template v-if="session.error"> {{ session.error }}</template>
            <template v-else-if="session.stale">
                {{ labels.staleEdit }}</template
            >
            <craft-button
                slot="action"
                type="button"
                variant="outline"
                size="small"
                inherit
                @click="discardEdit"
            >
                {{ labels.discardChanges }}
            </craft-button>
        </craft-callout>
        <craft-callout
            v-else-if="session"
            variant="warning"
            appearance="fill"
            rounded="none"
        >
            {{
                labels.beingEditedBy
                    .replace('{name}', session.userName ?? '')
                    .replace('{date}', session.since)
            }}
            <craft-button
                v-if="canEdit"
                slot="action"
                type="button"
                size="small"
                @click="takeOver"
            >
                {{ labels.takeOver }}
            </craft-button>
        </craft-callout>
        <craft-callout
            v-for="(notice, index) in notices"
            :key="index"
            :variant="notice.admin ? 'warning' : 'info'"
            appearance="fill"
            rounded="none"
        >
            {{ notice.message }}
            <craft-button
                v-if="editable && notice.id"
                slot="action"
                type="button"
                size="small"
                @click="dismissNotice(notice)"
            >
                {{ labels.dismiss }}
            </craft-button>
        </craft-callout>
    </component>

    <component
        :is="LayoutSlot"
        v-if="canStartEditing || session?.mine"
        name="additional-buttons"
    >
        <craft-button
            v-if="canStartEditing"
            type="button"
            variant="primary"
            @click="startEditing"
        >
            {{ labels.edit }}
        </craft-button>
        <craft-button
            v-else
            type="button"
            variant="primary"
            @click="reviewing = true"
        >
            {{ labels.reviewAndUpdate }}
        </craft-button>
    </component>

    <component :is="LayoutSlot" name="content-tabs">
        <craft-tabs :label="labels.orderTabs" @craft-tab-show="onTabShow">
            <craft-tab
                v-for="tab in tabs"
                :key="tab.id"
                slot="tab"
                :controls="`order-tab-${tab.id}`"
            >
                {{ tab.label }}
            </craft-tab>
        </craft-tabs>
    </component>

    <div class="py-lg">
        <component :is="CpContainer">
            <div
                id="order-tab-details"
                ref="detailsEl"
                :hidden="activeTab !== 'details'"
                class="relative grid gap-4"
            >
                <span
                    v-for="(bar, index) in changeBars"
                    :key="index"
                    :style="{
                        ...indicatorStyle,
                        ...changeKindStyles[bar.kind],
                        insetBlockStart: `${bar.top}px`,
                        height: `${bar.height}px`,
                    }"
                    :title="
                        bar.kind === 'added'
                            ? labels.changeAdded
                            : bar.kind === 'removed'
                            ? labels.changeRemoved
                            : labels.fieldModified
                    "
                />
                <craft-pane
                    appearance="plain"
                    :padding="0"
                    :label="labels.products"
                >
                    <ActionMenu
                        v-if="editable"
                        slot="header-actions"
                        :label="labels.add"
                        :actions="addActions"
                    >
                        <template #invoker="{label}">
                            <craft-button
                                slot="invoker"
                                type="button"
                                icon="plus"
                                size="small"
                            >
                                {{ label }}
                            </craft-button>
                        </template>
                    </ActionMenu>
                    <div v-if="lineItems.length" data-line-items>
                        <AdminTable :table="lineItemsTable" read-only />
                    </div>
                    <craft-empty
                        v-else
                        :label="labels.noProducts"
                        appearance="plain"
                    />
                </craft-pane>

                <craft-pane
                    appearance="plain"
                    :padding="0"
                    :label="labels.payment"
                >
                    <dl class="cp-metadata-list">
                        <div
                            v-for="row in totals"
                            :key="row.label"
                            class="cp-metadata-list__item"
                        >
                            <dt class="font-bold text-xs">{{ row.label }}</dt>
                            <dd>{{ row.amount }}</dd>
                        </div>
                        <div
                            v-for="(adjustment, index) in adjustments"
                            :key="`adjustment-${index}`"
                            class="cp-metadata-list__item"
                        >
                            <dt class="font-bold text-xs">
                                {{ adjustment.type }}: {{ adjustment.name }}
                            </dt>
                            <dd>{{ adjustment.amount }}</dd>
                        </div>
                        <div
                            class="cp-metadata-list__item"
                            :data-changed="totalChanged || undefined"
                        >
                            <dt class="font-bold text-xs">
                                {{ labels.total }}
                            </dt>
                            <dd class="font-bold">{{ totalPrice }}</dd>
                        </div>
                        <template v-if="payments">
                            <div class="cp-metadata-list__item">
                                <dt class="font-bold text-xs">
                                    {{ labels.paid }}
                                </dt>
                                <dd>{{ payments.paid }}</dd>
                            </div>
                            <div class="cp-metadata-list__item">
                                <dt class="font-bold text-xs">
                                    {{ labels.outstanding }}
                                </dt>
                                <dd>{{ payments.outstanding }}</dd>
                            </div>
                        </template>
                        <div
                            v-if="couponCode || editable"
                            class="cp-metadata-list__item"
                            :data-changed="
                                changes.includes('couponCode') || undefined
                            "
                        >
                            <dt class="font-bold text-xs">
                                {{ labels.couponCode }}
                            </dt>
                            <dd class="flex items-center gap-2">
                                <code v-if="couponCode">{{ couponCode }}</code>
                                <template v-else>{{ labels.none }}</template>
                                <TextFieldPopover
                                    v-if="editable"
                                    :title="labels.couponCode"
                                    :label="
                                        labels.editItem.replace(
                                            '{item}',
                                            labels.couponCode
                                        )
                                    "
                                    :value="couponCode ?? ''"
                                    :save-label="labels.save"
                                    :hint="labels.removeCouponHint"
                                    @submit="
                                        apply([
                                            {
                                                type: 'setCouponCode',
                                                couponCode: $event || null,
                                            },
                                        ])
                                    "
                                />
                            </dd>
                        </div>
                    </dl>
                </craft-pane>
            </div>

            <div
                v-for="tab in tabs.filter((tab) => tab.fieldsUi)"
                :id="`order-tab-${tab.id}`"
                :key="tab.id"
                :hidden="activeTab !== tab.id"
            >
                <craft-button
                    v-if="editable"
                    type="button"
                    icon="pencil"
                    size="small"
                    class="mb-4"
                    @click="openFieldsSlideout"
                >
                    {{ labels.editFields }}
                </craft-button>
                <craft-field-group>
                    <component
                        :is="UiRenderer"
                        :payload="tab.fieldsUi"
                        :modified="modifiedFields"
                        disabled
                    />
                </craft-field-group>
            </div>

            <div
                id="order-tab-transactions"
                :hidden="activeTab !== 'transactions'"
            >
                <craft-pane appearance="raised" :padding="0">
                    <AdminTable
                        v-if="transactions.length"
                        :table="transactionsTable"
                        read-only
                    />
                    <craft-empty
                        v-else
                        :label="labels.noTransactions"
                        appearance="plain"
                    />
                </craft-pane>
            </div>

            <div id="order-tab-history" :hidden="activeTab !== 'history'">
                <craft-pane appearance="raised" :padding="0">
                    <AdminTable
                        v-if="histories.length"
                        :table="historyTable"
                        read-only
                    />
                    <craft-empty
                        v-else
                        :label="labels.noHistory"
                        appearance="plain"
                    />
                </craft-pane>
            </div>
        </component>
    </div>

    <craft-dialog
        v-if="reviewing && session"
        open
        :label="labels.reviewChanges"
        @craft-after-hide="reviewing = false"
    >
        <p v-if="!session.operations.length">{{ labels.noChanges }}</p>
        <ul v-else class="grid gap-2">
            <li
                v-for="operation in session.operations"
                :key="operation.index"
                class="flex items-center justify-between gap-2"
            >
                {{ operation.label }}
                <craft-button
                    type="button"
                    size="small"
                    @click="removeChange(operation.index)"
                >
                    {{ labels.remove }}
                </craft-button>
            </li>
        </ul>
        <p class="mt-4 font-bold">
            {{ session.originalTotal }} → {{ session.newTotal }}
            <template v-if="session.balance"> · {{ session.balance }}</template>
        </p>
        <craft-button
            slot="footer"
            type="button"
            variant="primary"
            :disabled="!session.operations.length"
            @click="commitEdit"
        >
            {{ labels.updateOrder }}
        </craft-button>
    </craft-dialog>

    <craft-dialog
        v-if="snapshot"
        open
        :label="snapshot.title"
        @craft-after-hide="snapshot = null"
    >
        <div v-for="section in snapshot.sections" :key="section.label">
            <h3 class="font-bold">{{ section.label }}</h3>
            <pre><code>{{ section.json }}</code></pre>
        </div>
    </craft-dialog>

    <MetadataDetails :html="metadataHtml">
        <craft-field-group>
            <craft-field
                :status="changes.includes('customer') ? 'modified' : undefined"
                :status-label="labels.fieldModified"
            >
                <span slot="label">{{ labels.customer }}</span>
                <div slot="input" class="flex items-center gap-2">
                    <template v-if="customer">
                        <span v-html="customer.chipHtml" />
                    </template>
                    <template v-else>{{ email ?? labels.noCustomer }}</template>
                    <craft-button
                        v-if="editable"
                        type="button"
                        icon
                        size="small"
                        @click="openForm('setCustomer')"
                    >
                        <craft-icon
                            name="pencil"
                            :label="
                                labels.editItem.replace(
                                    '{item}',
                                    labels.customer
                                )
                            "
                        />
                    </craft-button>
                </div>
            </craft-field>
            <craft-field
                v-for="addressType in ['shipping', 'billing'] as const"
                :key="addressType"
                :status="
                    changes.includes(`${addressType}Address`)
                        ? 'modified'
                        : undefined
                "
                :status-label="labels.fieldModified"
            >
                <span slot="label">{{
                    addressType === 'shipping'
                        ? labels.shippingAddress
                        : labels.billingAddress
                }}</span>
                <div
                    slot="input"
                    class="flex items-start justify-between gap-2"
                >
                    <div>
                        <span
                            v-if="
                                addressType === 'shipping'
                                    ? shippingAddressHtml
                                    : billingAddressHtml
                            "
                            v-html="
                                addressType === 'shipping'
                                    ? shippingAddressHtml
                                    : billingAddressHtml
                            "
                        />
                        <template v-else>{{ labels.noAddress }}</template>
                    </div>
                    <ActionMenu
                        v-if="editable"
                        :label="labels.actions"
                        :actions="addressActions(addressType)"
                    />
                </div>
            </craft-field>
            <craft-field
                :status="
                    changes.includes('shippingMethod') ? 'modified' : undefined
                "
                :status-label="labels.fieldModified"
            >
                <span slot="label">{{ labels.shippingMethod }}</span>
                <div slot="input" class="flex items-center gap-2">
                    {{ shippingMethodName || labels.none }}
                    <ComboboxFieldPopover
                        v-if="editable"
                        :title="labels.shippingMethod"
                        :label="
                            labels.editItem.replace(
                                '{item}',
                                labels.shippingMethod
                            )
                        "
                        :options="shippingMethodOptions"
                        :value="shippingMethodHandle"
                        @select="selectShippingMethod"
                    />
                </div>
            </craft-field>
            <craft-field
                v-for="detail in details.filter((detail) => detail.form)"
                :key="detail.key"
                :status="changes.includes(detail.key) ? 'modified' : undefined"
                :status-label="labels.fieldModified"
            >
                <span slot="label">{{ detail.label }}</span>
                <div slot="input" class="flex items-center gap-2">
                    <span v-html="detail.value" />
                    <template v-if="editable">
                        <ComboboxFieldPopover
                            v-if="detail.key === 'orderSite'"
                            :title="detail.label"
                            :label="
                                labels.editItem.replace('{item}', detail.label)
                            "
                            :options="orderSiteOptions"
                            :value="orderSiteId"
                            required
                            @select="
                                apply([
                                    {
                                        type: 'setOrderSite',
                                        orderSiteId: Number($event),
                                    },
                                ])
                            "
                        />
                        <TextFieldPopover
                            v-else-if="detail.key === 'reference'"
                            :title="detail.label"
                            :label="
                                labels.editItem.replace('{item}', detail.label)
                            "
                            :value="reference ?? ''"
                            :save-label="labels.save"
                            @submit="
                                apply([
                                    {
                                        type: 'setReference',
                                        reference: $event || null,
                                    },
                                ])
                            "
                        />
                        <craft-button
                            v-else
                            type="button"
                            icon
                            size="small"
                            @click="openForm(detail.form!)"
                        >
                            <craft-icon
                                name="pencil"
                                :label="
                                    labels.editItem.replace(
                                        '{item}',
                                        detail.label
                                    )
                                "
                            />
                        </craft-button>
                    </template>
                </div>
            </craft-field>
        </craft-field-group>
        <dl
            class="cp-metadata-list"
            style="margin-block-start: var(--c-spacing-lg, 1rem)"
        >
            <div
                v-for="detail in details.filter((detail) => !detail.form)"
                :key="detail.key"
                class="cp-metadata-list__item"
            >
                <dt class="font-bold text-xs">{{ detail.label }}</dt>
                <dd><span v-html="detail.value" /></dd>
            </div>
        </dl>
    </MetadataDetails>
</template>

<style>
    [data-line-items] tbody tr[data-change='removed'] > td {
        opacity: 0.5;
    }
</style>

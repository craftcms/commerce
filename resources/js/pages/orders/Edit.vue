<script setup lang="ts">
    import {
        ActionMenu,
        ActivityTimeline,
        AdminTable,
        ElementActionMenu,
        MetadataDetails,
        ScreenPanel,
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
        id: number | null;
        uid: string | null;
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
        operations: Array<{
            index: number;
            label: string;
            summary: string | null;
        }>;
        originalTotal: string | null;
        newTotal: string | null;
        totals: Array<{
            label: string;
            amount: string;
            emphasis: boolean;
            divider: boolean;
        }>;
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
        orderStatusHtml: string | null;
        immediateActionsBlockedReason: string | null;
        canFulfil: boolean;
        canEmail: boolean;
        emails: Array<{id: number; name: string}>;
        fulfilment: Array<{
            item: string;
            location: string;
            committed: number;
            fulfilled: number;
        }>;
        snapshots: SnapshotRow[];
        pdfs: Array<{name: string; url: string}>;
        copyToUser: {
            userId: number;
            shippingAddressId: number | null;
            billingAddressId: number | null;
        } | null;
        completionStatusName: string | null;
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
        activity: {
            url: string;
            elementType: string;
            elementId: number;
            siteId: number | null;
        } | null;
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
    const canChangeStatus = computed(() => props.canEdit && props.isCompleted);
    const canComplete = computed(() => props.canEdit && !props.isCompleted);
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

    interface SnapshotRow {
        key: string;
        name: string;
        context: string | null;
        type: string;
    }

    const snapshotData = ref<Record<string, string>>({});
    const openSnapshots = ref<string[]>([]);

    async function toggleSnapshot(row: SnapshotRow): Promise<void> {
        if (openSnapshots.value.includes(row.key)) {
            openSnapshots.value = openSnapshots.value.filter(
                (key) => key !== row.key
            );

            return;
        }

        openSnapshots.value = [...openSnapshots.value, row.key];

        if (snapshotData.value[row.key] !== undefined) {
            return;
        }

        try {
            const {data} = await window.Craft.sendActionRequest<{json: string}>(
                'GET',
                props.editUrls.snapshot,
                {params: {orderId: props.orderId, key: row.key}}
            );
            snapshotData.value = {...snapshotData.value, [row.key]: data.json};
        } catch (error: any) {
            openSnapshots.value = openSnapshots.value.filter(
                (key) => key !== row.key
            );
            showError(error);
        }
    }

    function downloadPdf(pdf: {url: string}): void {
        window.location.href = pdf.url;
    }

    const activityRefresh = ref(0);
    watch(
        () => props.dateUpdated,
        () => activityRefresh.value++
    );

    const selectedEmailId = ref<number | null>(props.emails[0]?.id ?? null);

    async function sendSelectedEmail(): Promise<void> {
        if (selectedEmailId.value !== null) {
            await post(props.editUrls.sendEmail, {
                emailId: selectedEmailId.value,
            });
            activityRefresh.value++;
        }
    }

    function openFulfilment(): void {
        void openUiModal({
            modalUrl: props.editUrls.fulfillmentForm,
            actionUrl: props.editUrls.fulfill,
            params: {orderId: String(props.orderId)},
            onSubmitted: () => {
                activityRefresh.value++;
                window.Cp.$router.reload();
            },
        });
    }

    const sharedCartUrl = ref<string | null>(null);

    async function shareCart(): Promise<void> {
        try {
            const {data} = await window.Craft.sendActionRequest<{url: string}>(
                'GET',
                props.editUrls.shareCart,
                {params: {orderId: props.orderId}}
            );
            sharedCartUrl.value = data.url;
        } catch (error: any) {
            showError(error);
        }
    }

    async function copySharedCartUrl(): Promise<void> {
        if (sharedCartUrl.value) {
            await navigator.clipboard.writeText(sharedCartUrl.value);
            window.Craft.cp?.displayNotice(props.labels.linkCopied);
        }
    }

    function copyableAddressId(
        addressType: 'shipping' | 'billing'
    ): number | null {
        return (
            (addressType === 'shipping'
                ? props.copyToUser?.shippingAddressId
                : props.copyToUser?.billingAddressId) ?? null
        );
    }

    async function copyAddressToUser(
        addressType: 'shipping' | 'billing'
    ): Promise<void> {
        await post(props.editUrls.copyAddressToUser, {
            addressId: copyableAddressId(addressType),
            userId: props.copyToUser?.userId,
        });
        window.Craft.cp?.displayNotice(props.labels.addressCopied);
    }

    function recalculate(): void {
        if (window.confirm(props.labels.confirmRecalculate)) {
            void apply([{type: 'recalculate'}]);
        }
    }

    function adjustmentTarget(adjustment: Adjustment): Record<string, string> {
        return adjustment.id !== null
            ? {adjustmentId: String(adjustment.id)}
            : {adjustmentUid: adjustment.uid ?? ''};
    }

    function adjustmentActions(adjustment: Adjustment): ActionItem[] {
        return [
            {
                label: props.labels.editAdjustment,
                icon: 'pencil',
                onClick: () =>
                    openForm('updateAdjustment', adjustmentTarget(adjustment)),
            },
            {
                label: props.labels.removeAdjustment,
                icon: 'trash',
                variant: 'danger',
                onClick: () =>
                    void apply([
                        {
                            type: 'removeAdjustment',
                            ...adjustmentTarget(adjustment),
                        },
                    ]),
            },
        ];
    }

    const totalsActions = computed<ActionItem[]>(() => [
        {
            label: props.labels.addAdjustment,
            onClick: () => openForm('addAdjustment'),
        },
        {label: props.labels.recalculate, onClick: recalculate},
    ]);

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

    function changeStatus(): void {
        void openUiModal({
            modalUrl: props.editUrls.statusForm,
            actionUrl: props.editUrls.status,
            params: {orderId: String(props.orderId)},
            onSubmitted: () => window.Cp.$router.reload(),
        });
    }

    async function completeCart(): Promise<void> {
        const message = props.labels.confirmComplete.replace(
            '{status}',
            props.completionStatusName ?? ''
        );

        if (
            window.confirm(message) &&
            (await post(props.editUrls.complete, {}))
        ) {
            window.Cp.$router.reload();
        }
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
            ...(copyableAddressId(addressType) ||
            (props.copyToUser && props.immediateActionsBlockedReason)
                ? [
                      {
                          label: props.labels.copyToCustomer,
                          disabled: !!props.immediateActionsBlockedReason,
                          disabledReason: props.immediateActionsBlockedReason,
                          onClick: () => void copyAddressToUser(addressType),
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
                  ...(props.isCompleted
                      ? [
                            {
                                label: props.labels.addAdjustment,
                                onClick: () =>
                                    openForm('addAdjustment', {
                                        lineItemUid: item.uid ?? '',
                                    }),
                            },
                        ]
                      : []),
              ]
            : [];

        return [
            ...editActions,
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
    ]);

    const noteItems = computed(() =>
        props.lineItems.filter((item) => item.uid && item.change !== 'removed')
    );
    const fulfilmentLocations = computed(
        () => new Set(props.fulfilment.map((row) => row.location)).size
    );
    const hasCommunication = computed(
        () => props.canEmail || props.pdfs.length > 0 || !props.isCompleted
    );
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
                                        {
                                            class: 'text-sm flex items-center gap-1',
                                        },
                                        [
                                            `${adjustment.type}: ${adjustment.name} ${adjustment.amount}`,
                                            editable.value &&
                                            props.isCompleted &&
                                            (adjustment.id || adjustment.uid) &&
                                            item.change !== 'removed'
                                                ? h(ActionMenu, {
                                                      actions:
                                                          adjustmentActions(
                                                              adjustment
                                                          ),
                                                      label: props.labels
                                                          .actions,
                                                  })
                                                : null,
                                        ]
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
                        row.original.change === 'removed' ||
                        lineItemActions(row.original).length === 0
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
        <ElementActionMenu v-if="actionMenu.length" :items="actionMenu" />
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
        v-if="
            canStartEditing ||
            session?.mine ||
            canChangeStatus ||
            canComplete ||
            !isCompleted
        "
        name="additional-buttons"
    >
        <craft-button
            v-if="canComplete"
            type="button"
            variant="primary"
            @click="completeCart"
        >
            {{ labels.markAsCompleted }}
        </craft-button>
        <craft-button
            v-if="canStartEditing"
            type="button"
            variant="primary"
            @click="startEditing"
        >
            {{ labels.edit }}
        </craft-button>
        <craft-button
            v-else-if="session?.mine"
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
                    <craft-button
                        v-if="canFulfil"
                        slot="header-actions"
                        type="button"
                        size="small"
                        :disabled="!!immediateActionsBlockedReason || undefined"
                        :title="immediateActionsBlockedReason ?? undefined"
                        @click="openFulfilment"
                    >
                        {{ labels.fulfil }}
                    </craft-button>
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
                    <ActionMenu
                        v-if="editable && isCompleted"
                        slot="header-actions"
                        :label="labels.adjustments"
                        :actions="totalsActions"
                    >
                        <template #invoker="{label}">
                            <craft-button
                                slot="invoker"
                                type="button"
                                size="small"
                            >
                                {{ label }}
                            </craft-button>
                        </template>
                    </ActionMenu>
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
                            <dd class="flex items-center gap-2">
                                {{ adjustment.amount }}
                                <ActionMenu
                                    v-if="
                                        editable &&
                                        isCompleted &&
                                        (adjustment.id || adjustment.uid)
                                    "
                                    :label="labels.actions"
                                    :actions="adjustmentActions(adjustment)"
                                />
                            </dd>
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
                class="order-panel__row"
            >
                <div>
                    {{ operation.label }}
                    <div
                        v-if="operation.summary"
                        style="
                            font-size: 0.875em;
                            color: var(
                                --c-text-muted,
                                var(--gray-600, #6b7280)
                            );
                        "
                    >
                        {{ operation.summary }}
                    </div>
                </div>
                <craft-button
                    type="button"
                    size="small"
                    variant="danger-plain"
                    @click="removeChange(operation.index)"
                >
                    <craft-icon name="xmark-large" :label="labels.remove" />
                </craft-button>
            </li>
        </ul>
        <dl v-if="session.totals.length" class="review-totals">
            <div
                v-for="row in session.totals"
                :key="row.label"
                class="review-totals__row"
                :class="{
                    'review-totals__divider': row.divider,
                    'review-totals__emphasis': row.emphasis,
                }"
            >
                <dt>{{ row.label }}</dt>
                <dd>{{ row.amount }}</dd>
            </div>
        </dl>
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
        v-if="sharedCartUrl"
        open
        :label="labels.shareCart"
        @craft-after-hide="sharedCartUrl = null"
    >
        <p>{{ labels.shareCartHint }}</p>
        <div class="flex items-center gap-2">
            <input
                class="text"
                style="flex: 1; min-width: 0"
                type="text"
                readonly
                :value="sharedCartUrl"
                @focus="($event.target as HTMLInputElement).select()"
            />
            <craft-button type="button" @click="copySharedCartUrl">
                {{ labels.copy }}
            </craft-button>
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
            <craft-field v-if="orderStatusHtml">
                <span slot="label">{{ labels.status }}</span>
                <div slot="input" class="flex items-center gap-2">
                    <span v-html="orderStatusHtml" />
                    <craft-button
                        v-if="canChangeStatus"
                        type="button"
                        icon
                        size="small"
                        :disabled="!!immediateActionsBlockedReason"
                        :title="immediateActionsBlockedReason ?? undefined"
                        @click="changeStatus"
                    >
                        <craft-icon
                            name="pencil"
                            :label="
                                labels.editItem.replace('{item}', labels.status)
                            "
                        />
                    </craft-button>
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

    <ScreenPanel
        id="addresses"
        :label="labels.addresses"
        icon="location-dot"
        :order="10"
    >
        <div class="order-panel">
            <craft-field-group>
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
                        changes.includes('shippingMethod')
                            ? 'modified'
                            : undefined
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
            </craft-field-group>
        </div>
    </ScreenPanel>

    <ScreenPanel
        v-if="isCompleted"
        id="fulfilment"
        :label="labels.fulfilment"
        icon="truck"
        :order="20"
    >
        <div class="order-panel">
            <p class="order-panel__hint">{{ labels.fulfilmentHint }}</p>
            <p v-if="!fulfilment.length" class="text-sm">
                {{ labels.noFulfilment }}
            </p>
            <table v-else class="order-panel__table">
                <thead>
                    <tr>
                        <th scope="col">{{ labels.item }}</th>
                        <th v-if="fulfilmentLocations > 1" scope="col">
                            {{ labels.location }}
                        </th>
                        <th scope="col" class="order-panel__number">
                            {{ labels.committed }}
                        </th>
                        <th scope="col" class="order-panel__number">
                            {{ labels.fulfilledHeading }}
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(row, index) in fulfilment" :key="index">
                        <td>{{ row.item }}</td>
                        <td v-if="fulfilmentLocations > 1">
                            {{ row.location }}
                        </td>
                        <td class="order-panel__number">
                            {{ row.committed }}
                        </td>
                        <td class="order-panel__number">
                            {{ row.fulfilled }}
                        </td>
                    </tr>
                </tbody>
            </table>
            <template v-if="canFulfil">
                <p v-if="immediateActionsBlockedReason" class="text-sm">
                    {{ immediateActionsBlockedReason }}
                </p>
                <craft-button
                    type="button"
                    variant="primary"
                    :disabled="!!immediateActionsBlockedReason || undefined"
                    @click="openFulfilment"
                >
                    {{ labels.fulfil }}
                </craft-button>
            </template>
        </div>
    </ScreenPanel>

    <ScreenPanel
        v-if="hasCommunication"
        id="communication"
        :label="labels.communication"
        icon="envelope"
        :order="30"
    >
        <div class="order-panel">
            <p
                v-if="
                    immediateActionsBlockedReason &&
                    (emails.length || pdfs.length)
                "
                class="text-sm"
            >
                {{ immediateActionsBlockedReason }}
            </p>

            <section v-if="emails.length" class="order-panel__section">
                <h4>{{ labels.emails }}</h4>
                <p class="order-panel__hint">{{ labels.emailsHint }}</p>
                <craft-select v-if="emails.length > 1" :label="labels.email">
                    <select
                        slot="input"
                        :disabled="!!immediateActionsBlockedReason"
                        @change="
                            selectedEmailId = Number(
                                ($event.target as HTMLSelectElement).value
                            )
                        "
                    >
                        <option
                            v-for="email in emails"
                            :key="email.id"
                            :value="email.id"
                            :selected="email.id === selectedEmailId"
                        >
                            {{ email.name }}
                        </option>
                    </select>
                </craft-select>
                <p v-else class="font-bold text-sm">{{ emails[0].name }}</p>
                <craft-button
                    type="button"
                    :disabled="!!immediateActionsBlockedReason || undefined"
                    @click="sendSelectedEmail"
                >
                    <craft-icon name="paper-plane" slot="prefix" />
                    {{ labels.send }}
                </craft-button>
            </section>

            <section v-if="pdfs.length" class="order-panel__section">
                <h4>{{ labels.pdfs }}</h4>
                <p class="order-panel__hint">{{ labels.pdfsHint }}</p>
                <ul class="order-panel__list">
                    <li
                        v-for="pdf in pdfs"
                        :key="pdf.url"
                        class="order-panel__row"
                    >
                        <span>{{ pdf.name }}</span>
                        <craft-button
                            type="button"
                            size="small"
                            :disabled="
                                !!immediateActionsBlockedReason || undefined
                            "
                            @click="downloadPdf(pdf)"
                        >
                            <craft-icon name="download" slot="prefix" />
                            {{ labels.download }}
                        </craft-button>
                    </li>
                </ul>
            </section>

            <section v-if="!isCompleted" class="order-panel__section">
                <h4>{{ labels.shareCartHeading }}</h4>
                <p class="order-panel__hint">{{ labels.shareCartHint }}</p>
                <craft-button type="button" @click="shareCart">
                    {{ labels.shareCart }}
                </craft-button>
            </section>
        </div>
    </ScreenPanel>

    <ScreenPanel
        id="notes"
        :label="labels.notes"
        icon="note-sticky"
        :order="40"
    >
        <div class="order-panel">
            <p class="order-panel__hint">{{ labels.notesHint }}</p>
            <craft-empty
                v-if="!noteItems.length"
                :label="labels.noProducts"
                appearance="plain"
            />
            <ul v-else class="order-panel__list">
                <li
                    v-for="item in noteItems"
                    :key="item.uid ?? item.sku"
                    class="order-panel__item"
                >
                    <div class="order-panel__row">
                        <strong class="text-sm">{{ item.description }}</strong>
                        <craft-button
                            v-if="editable"
                            type="button"
                            size="small"
                            @click="
                                openForm('setLineItemNotes', {
                                    lineItemUid: item.uid ?? '',
                                })
                            "
                        >
                            {{
                                item.note || item.privateNote
                                    ? labels.edit
                                    : labels.addNote
                            }}
                        </craft-button>
                    </div>
                    <div v-if="item.note" class="order-note">
                        <craft-badge fill="blue" size="small">
                            {{ labels.customer }}
                        </craft-badge>
                        <span>{{ item.note }}</span>
                    </div>
                    <div v-if="item.privateNote" class="order-note">
                        <craft-badge size="small">
                            <craft-icon name="lock" slot="prefix" />
                            {{ labels.internal }}
                        </craft-badge>
                        <span>{{ item.privateNote }}</span>
                    </div>
                    <p
                        v-if="!item.note && !item.privateNote"
                        class="order-panel__hint"
                    >
                        {{ labels.noNote }}
                    </p>
                </li>
            </ul>
        </div>
    </ScreenPanel>

    <ScreenPanel
        v-if="activity"
        id="history"
        :label="labels.history"
        icon="clock-rotate-left"
        :order="50"
        scrolls-itself
        v-slot="{active}"
    >
        <div class="order-activity">
            <div class="order-activity__timeline">
                <ActivityTimeline
                    :active="active"
                    :url="activity.url"
                    :element-type="activity.elementType"
                    :element-id="activity.elementId"
                    :site-id="activity.siteId"
                    :refresh-token="activityRefresh"
                />
            </div>
            <details v-if="histories.length" class="order-activity__earlier">
                <summary>
                    {{ labels.earlierHistory }} ({{ histories.length }})
                </summary>
                <ol class="order-panel__list">
                    <li
                        v-for="(history, index) in histories"
                        :key="index"
                        class="order-panel__item"
                    >
                        <div class="flex items-center gap-2 flex-wrap">
                            <span v-html="history.newStatusHtml ?? ''" />
                            <span
                                v-if="history.prevStatusHtml"
                                class="text-xs flex items-center gap-1"
                            >
                                {{ labels.previousStatus }}:
                                <span v-html="history.prevStatusHtml" />
                            </span>
                        </div>
                        <div
                            v-if="history.messageHtml"
                            class="text-sm"
                            v-html="history.messageHtml"
                        />
                        <div class="text-xs flex items-center gap-2 flex-wrap">
                            <span v-html="history.userHtml" />
                            <span>{{ history.date }}</span>
                        </div>
                    </li>
                </ol>
            </details>
        </div>
    </ScreenPanel>

    <ScreenPanel
        v-if="snapshots.length"
        id="snapshots"
        :label="labels.snapshots"
        icon="camera"
        :order="60"
    >
        <div class="order-panel">
            <p class="order-panel__hint">{{ labels.snapshotsHint }}</p>
            <p v-if="immediateActionsBlockedReason" class="text-sm">
                {{ immediateActionsBlockedReason }}
            </p>
            <table class="order-panel__table">
                <thead>
                    <tr>
                        <th scope="col">{{ labels.name }}</th>
                        <th scope="col">{{ labels.type }}</th>
                    </tr>
                </thead>
                <tbody v-for="row in snapshots" :key="row.key">
                    <tr>
                        <td>
                            <button
                                type="button"
                                class="order-panel__toggle"
                                :aria-expanded="openSnapshots.includes(row.key)"
                                :disabled="!!immediateActionsBlockedReason"
                                @click="toggleSnapshot(row)"
                            >
                                <craft-icon
                                    :name="
                                        openSnapshots.includes(row.key)
                                            ? 'chevron-down'
                                            : 'chevron-right'
                                    "
                                    :label="
                                        openSnapshots.includes(row.key)
                                            ? labels.hideData
                                            : labels.showData
                                    "
                                />
                                <span>
                                    {{ row.name }}
                                    <small v-if="row.context" class="block">
                                        {{ row.context }}
                                    </small>
                                </span>
                            </button>
                        </td>
                        <td>{{ row.type }}</td>
                    </tr>
                    <tr v-if="openSnapshots.includes(row.key)">
                        <td colspan="2">
                            <pre
                                v-if="snapshotData[row.key] !== undefined"
                                class="order-panel__code"
                            ><code>{{ snapshotData[row.key] }}</code></pre>
                            <p v-else class="text-sm">
                                {{ labels.loadingData }}
                            </p>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </ScreenPanel>
</template>

<style>
    .order-panel {
        display: grid;
        gap: var(--c-spacing-lg, 1rem);
        padding: var(--c-spacing-lg, 1rem) var(--cp-container-padding, 1rem);
    }

    .order-panel__list {
        display: grid;
        gap: var(--c-spacing-md, 0.75rem);
        margin: 0;
        padding: 0;
        list-style: none;
    }

    .order-panel__item dt,
    .order-panel__item dd {
        margin: 0;
    }

    .order-panel__item + .order-panel__item {
        padding-block-start: var(--c-spacing-md, 0.75rem);
        border-block-start: 1px solid var(--c-color-border-quiet, #e5e7eb);
    }

    .order-activity {
        display: flex;
        flex-direction: column;
        block-size: 100%;
        min-block-size: 0;
    }

    .order-activity__timeline {
        flex: 1;
        min-block-size: 0;
    }

    .order-activity__earlier {
        max-block-size: 45%;
        padding: var(--c-spacing-md, 0.75rem) var(--cp-container-padding, 1rem);
        overflow: auto;
        border-block-start: 1px solid var(--c-color-border-quiet, #e5e7eb);
    }

    .order-activity__earlier summary {
        cursor: pointer;
        font-weight: 700;
    }

    .order-activity__earlier .order-panel__list {
        margin-block-start: var(--c-spacing-md, 0.75rem);
    }

    .order-panel__row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: var(--c-spacing-sm, 0.5rem);
    }

    .order-panel__hint {
        margin: 0;
        color: var(--c-color-text-quiet, #64748b);
        font-size: var(--text-sm, 0.875rem);
    }

    .order-panel__section {
        display: grid;
        gap: var(--c-spacing-sm, 0.5rem);
        justify-items: start;
    }

    .order-panel__section h4,
    .order-panel__section p {
        margin: 0;
    }

    .order-panel__section + .order-panel__section {
        padding-block-start: var(--c-spacing-lg, 1rem);
        border-block-start: 1px solid var(--c-color-border-quiet, #e5e7eb);
    }

    .order-panel__section craft-select {
        inline-size: 100%;
    }

    .order-panel__table {
        inline-size: 100%;
        border-collapse: collapse;
        font-variant-numeric: tabular-nums;
    }

    .order-panel__table th,
    .order-panel__table td {
        padding: var(--c-spacing-sm, 0.5rem) var(--c-spacing-xs, 0.25rem);
        border-block-end: 1px solid var(--c-color-border-quiet, #e5e7eb);
        text-align: start;
        vertical-align: top;
    }

    .order-panel__table th {
        font-size: var(--text-xs, 0.75rem);
        font-weight: 700;
    }

    .order-panel__number {
        text-align: end !important;
    }

    .order-panel__toggle {
        display: flex;
        align-items: flex-start;
        gap: var(--c-spacing-xs, 0.25rem);
        padding: 0;
        border: 0;
        background: none;
        color: inherit;
        font: inherit;
        text-align: start;
        cursor: pointer;
    }

    .order-panel__toggle:disabled {
        cursor: not-allowed;
        opacity: 0.6;
    }

    .order-panel__code {
        max-block-size: 20rem;
        margin: 0;
        overflow: auto;
        white-space: pre-wrap;
        overflow-wrap: anywhere;
        font-size: var(--text-xs, 0.75rem);
    }

    .order-note {
        display: grid;
        justify-items: start;
        gap: var(--c-spacing-xs, 0.25rem);
        margin-block-start: var(--c-spacing-sm, 0.5rem);
    }

    .order-panel__actions {
        display: grid;
        justify-items: start;
        gap: var(--c-spacing-sm, 0.5rem);
    }

    .review-totals {
        display: grid;
        gap: 0.25rem;
        margin: 1rem 0 0;
        font-variant-numeric: tabular-nums;
    }

    .review-totals__row {
        display: flex;
        justify-content: space-between;
        gap: 2rem;
    }

    .review-totals dt,
    .review-totals dd {
        margin: 0;
    }

    .review-totals__divider {
        margin-block-start: 0.25rem;
        padding-block-start: 0.5rem;
        border-block-start: 1px solid var(--c-border-color, #d1d5db);
    }

    .review-totals__emphasis {
        font-weight: bold;
    }

    [data-line-items] tbody tr[data-change='removed'] > td {
        opacity: 0.5;
    }
</style>

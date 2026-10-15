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
    import {computed, h, ref, resolveComponent} from 'vue';

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
        editUrls: {form: string; apply: string};
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
        customer: {chipHtml: string; email: string | null} | null;
        email: string | null;
        shippingAddressHtml: string | null;
        billingAddressHtml: string | null;
        details: Array<{label: string; value: string; form: string | null}>;
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

    const editable = computed(() => props.canEdit && !props.isCompleted);

    function editParams(): Record<string, string> {
        return {
            orderId: String(props.orderId),
            dateUpdated: props.dateUpdated ?? '',
        };
    }

    function openForm(form: string, params: Record<string, string> = {}): void {
        void openUiModal({
            modalUrl: props.editUrls.form,
            actionUrl: props.editUrls.form,
            params: {...editParams(), form, ...params},
            onSubmitted: () => window.Cp.$router.reload(),
        });
    }

    async function apply(
        operations: Array<Record<string, unknown>>
    ): Promise<void> {
        try {
            const {data} = await window.Craft.sendActionRequest<{
                message?: string;
            }>('POST', props.editUrls.apply, {
                data: {...editParams(), operations},
            });

            if (data.message) {
                window.Craft.cp?.displayNotice(data.message);
            }
        } catch (error: any) {
            window.Craft.cp?.displayError(error?.response?.data?.message);
        }

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
                onClick: () => openForm('setAddress', {addressType}),
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

        return [
            {
                label: props.labels.edit,
                icon: 'pencil',
                onClick: () => openForm('updateLineItem', params),
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
            ...(item.url
                ? [
                      {
                          type: 'link' as const,
                          href: item.url,
                          label: props.labels.viewPurchasable,
                      },
                  ]
                : []),
            {
                label: props.labels.remove,
                icon: 'trash',
                variant: 'danger',
                onClick: () => removeLineItem(item),
            },
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

                        return h('div', [
                            item.url
                                ? h(
                                      'a',
                                      {href: item.url, class: 'font-bold'},
                                      item.description
                                  )
                                : h(
                                      'span',
                                      {class: 'font-bold'},
                                      item.description
                                  ),
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
                        ]);
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
                ...(editable.value
                    ? [
                          lineItemColumns.display({
                              id: 'actions',
                              cell: ({row}) =>
                                  h(ActionMenu, {
                                      actions: lineItemActions(row.original),
                                      label: props.labels.actions,
                                  }),
                          }),
                      ]
                    : []),
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

    <component :is="LayoutSlot" v-if="notices.length" name="content-notices">
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
                :hidden="activeTab !== 'details'"
                class="grid gap-4"
            >
                <craft-pane appearance="raised" :label="labels.products">
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
                    <AdminTable
                        v-if="lineItems.length"
                        :table="lineItemsTable"
                        read-only
                    />
                    <craft-empty
                        v-else
                        :label="labels.noProducts"
                        appearance="plain"
                    />
                </craft-pane>

                <craft-pane appearance="raised" :label="labels.payment">
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
                        <div class="cp-metadata-list__item">
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
                        >
                            <dt class="font-bold text-xs">
                                {{ labels.couponCode }}
                            </dt>
                            <dd class="flex items-center gap-2">
                                <code v-if="couponCode">{{ couponCode }}</code>
                                <template v-else>{{ labels.none }}</template>
                                <craft-button
                                    v-if="editable"
                                    type="button"
                                    icon
                                    size="small"
                                    @click="openForm('setCouponCode')"
                                >
                                    <craft-icon
                                        name="pencil"
                                        :label="
                                            labels.editItem.replace(
                                                '{item}',
                                                labels.couponCode
                                            )
                                        "
                                    />
                                </craft-button>
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
                    @click="openForm('setFieldValues')"
                >
                    {{ labels.editFields }}
                </craft-button>
                <craft-field-group>
                    <component
                        :is="UiRenderer"
                        :payload="tab.fieldsUi"
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

    <MetadataDetails :html="metadataHtml">
        <dl class="cp-metadata-list">
            <div class="cp-metadata-list__item">
                <dt class="font-bold text-xs">{{ labels.customer }}</dt>
                <dd class="flex items-center gap-2">
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
                </dd>
            </div>
            <div class="cp-metadata-list__item">
                <dt class="font-bold text-xs">{{ labels.shippingAddress }}</dt>
                <dd class="flex items-start justify-between gap-2">
                    <div>
                        <span
                            v-if="shippingAddressHtml"
                            v-html="shippingAddressHtml"
                        />
                        <template v-else>{{ labels.noAddress }}</template>
                    </div>
                    <ActionMenu
                        v-if="editable"
                        :label="labels.actions"
                        :actions="addressActions('shipping')"
                    />
                </dd>
            </div>
            <div class="cp-metadata-list__item">
                <dt class="font-bold text-xs">{{ labels.billingAddress }}</dt>
                <dd class="flex items-start justify-between gap-2">
                    <div>
                        <span
                            v-if="billingAddressHtml"
                            v-html="billingAddressHtml"
                        />
                        <template v-else>{{ labels.noAddress }}</template>
                    </div>
                    <ActionMenu
                        v-if="editable"
                        :label="labels.actions"
                        :actions="addressActions('billing')"
                    />
                </dd>
            </div>
            <div class="cp-metadata-list__item">
                <dt class="font-bold text-xs">{{ labels.shippingMethod }}</dt>
                <dd class="flex items-center gap-2">
                    {{ shippingMethodName || labels.none }}
                    <craft-button
                        v-if="editable"
                        type="button"
                        icon
                        size="small"
                        @click="openForm('setShippingMethod')"
                    >
                        <craft-icon
                            name="pencil"
                            :label="
                                labels.editItem.replace(
                                    '{item}',
                                    labels.shippingMethod
                                )
                            "
                        />
                    </craft-button>
                </dd>
            </div>
            <div
                v-for="detail in details"
                :key="detail.label"
                class="cp-metadata-list__item"
            >
                <dt class="font-bold text-xs">{{ detail.label }}</dt>
                <dd class="flex items-center gap-2">
                    <span v-html="detail.value" />
                    <craft-button
                        v-if="editable && detail.form"
                        type="button"
                        icon
                        size="small"
                        @click="openForm(detail.form)"
                    >
                        <craft-icon
                            name="pencil"
                            :label="
                                labels.editItem.replace('{item}', detail.label)
                            "
                        />
                    </craft-button>
                </dd>
            </div>
        </dl>
    </MetadataDetails>
</template>

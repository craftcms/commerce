<script setup lang="ts">
    import {displayNotice, sendActionRequest} from './cp-client';
    import {
        AdminTable,
        createCraftColumnHelper,
        useCraftTable,
    } from '@craftcms/cp';
    import {computed, h, onBeforeUnmount, ref, watch} from 'vue';

    interface PurchasableRow {
        id: number;
        type: string;
        typeLabel: string;
        description: string;
        sku: string;
        price: number | null;
        priceAsCurrency: string;
        stock: number | null;
        isAvailable: boolean;
        unavailableReason: string | null;
    }

    interface SearchResponse {
        data: PurchasableRow[];
        total: number;
        types: Array<{type: string; label: string}>;
        priceSortedBy: 'live' | 'base';
    }

    interface Selection {
        qty: string;
    }

    const props = defineProps<{
        orderId: number;
        searchUrl: string;
        submitUrl: string;
        params: Record<string, string>;
        labels: Record<string, string>;
    }>();

    const emit = defineEmits<{
        close: [];
        added: [];
    }>();

    const SEARCH_DELAY = 250;

    const rows = ref<PurchasableRow[]>([]);
    const total = ref(0);
    const types = ref<Array<{type: string; label: string}>>([]);
    const priceSortedBy = ref<'live' | 'base'>('live');
    const loading = ref(false);
    const submitting = ref(false);
    const loadError = ref<string | null>(null);
    const submitError = ref<string | null>(null);
    const search = ref('');
    const appliedSearch = ref('');
    const typeFilter = ref('');
    const sorting = ref([{id: 'description', desc: false}]);
    const pagination = ref({pageIndex: 0, pageSize: 25});
    const selected = ref<Record<number, Selection>>({});
    const rowErrors = ref<Record<number, string>>({});
    let searchTimer: ReturnType<typeof setTimeout> | undefined;
    let request = 0;

    const selectedIds = computed(() => Object.keys(selected.value).map(Number));
    const validQuantities = computed(() =>
        Object.values(selected.value).every((item) => isValidQty(item.qty))
    );
    const canSubmit = computed(
        () =>
            selectedIds.value.length > 0 &&
            validQuantities.value &&
            !submitting.value
    );
    const from = computed(() =>
        total.value === 0
            ? 0
            : pagination.value.pageIndex * pagination.value.pageSize + 1
    );
    const to = computed(() =>
        Math.min(
            total.value,
            (pagination.value.pageIndex + 1) * pagination.value.pageSize
        )
    );

    function isValidQty(qty: string): boolean {
        return /^[1-9]\d*$/.test(qty.trim());
    }

    function toggle(row: PurchasableRow, checked: boolean): void {
        const next = {...selected.value};
        const errors = {...rowErrors.value};

        delete errors[row.id];
        rowErrors.value = errors;

        if (checked && row.isAvailable) {
            next[row.id] = {qty: next[row.id]?.qty ?? '1'};
        } else {
            delete next[row.id];
        }

        selected.value = next;
    }

    function setQty(row: PurchasableRow, value: unknown): void {
        if (!selected.value[row.id]) {
            return;
        }

        selected.value = {
            ...selected.value,
            [row.id]: {qty: String(value ?? '')},
        };
    }

    const columnHelper = createCraftColumnHelper<PurchasableRow>();
    const columns = computed(() => [
        columnHelper.display({
            id: 'select',
            header: '',
            enableSorting: false,
            cell: ({row}) =>
                h('input', {
                    type: 'checkbox',
                    checked: Boolean(selected.value[row.original.id]),
                    disabled: !row.original.isAvailable,
                    title: row.original.unavailableReason ?? undefined,
                    'aria-label': row.original.description,
                    onChange: (event: Event) =>
                        toggle(
                            row.original,
                            (event.target as HTMLInputElement).checked
                        ),
                }),
        }),
        columnHelper.accessor('description', {
            id: 'description',
            header: props.labels.description,
            enableSorting: true,
            cell: ({row}) =>
                h('div', [
                    h('div', {class: 'font-bold'}, row.original.description),
                    rowErrors.value[row.original.id]
                        ? h(
                              'div',
                              {role: 'alert', class: 'add-line-item__error'},
                              rowErrors.value[row.original.id]
                          )
                        : null,
                ]),
        }),
        columnHelper.accessor('sku', {
            id: 'sku',
            header: props.labels.sku,
            enableSorting: true,
            cell: ({row}) => h('code', row.original.sku),
        }),
        columnHelper.accessor('typeLabel', {
            id: 'type',
            header: props.labels.type,
            enableSorting: true,
            cell: ({row}) => row.original.typeLabel,
        }),
        columnHelper.accessor('priceAsCurrency', {
            id: 'price',
            header: props.labels.price,
            enableSorting: true,
            meta: {
                headerTip:
                    priceSortedBy.value === 'live'
                        ? props.labels.priceSortedByLive
                        : props.labels.priceSortedByBase,
            },
            cell: ({row}) => row.original.priceAsCurrency,
        }),
        columnHelper.display({
            id: 'stock',
            header: props.labels.stock,
            enableSorting: false,
            cell: ({row}) =>
                row.original.stock === null ? '—' : String(row.original.stock),
        }),
        columnHelper.display({
            id: 'availability',
            header: props.labels.available,
            enableSorting: false,
            cell: ({row}) =>
                row.original.isAvailable
                    ? props.labels.available
                    : row.original.unavailableReason ??
                      props.labels.unavailable,
        }),
        columnHelper.display({
            id: 'qty',
            header: props.labels.quantity,
            enableSorting: false,
            cell: ({row}) => {
                const item = selected.value[row.original.id];

                if (!item) {
                    return null;
                }

                return h('craft-input', {
                    type: 'number',
                    min: '1',
                    step: '1',
                    label: props.labels.quantity,
                    labelSrOnly: true,
                    style: 'width: 5.5rem',
                    modelValue: item.qty,
                    onModelValueChanged: (event: Event) =>
                        setQty(
                            row.original,
                            (
                                event.target as HTMLInputElement & {
                                    modelValue?: unknown;
                                }
                            ).modelValue
                        ),
                });
            },
        }),
    ]);

    const table = useCraftTable<PurchasableRow>({
        get data() {
            return rows.value;
        },
        get columns() {
            return columns.value;
        },
        getRowId: (row) => String(row.id),
        manualPagination: true,
        manualSorting: true,
        enableSorting: true,
        enableMultiSort: false,
        enableSortingRemoval: false,
        get rowCount() {
            return total.value;
        },
        state: {
            get pagination() {
                return pagination.value;
            },
            get sorting() {
                return sorting.value;
            },
        },
        onPaginationChange: (updater) => {
            pagination.value =
                typeof updater === 'function'
                    ? updater(pagination.value)
                    : updater;
        },
        onSortingChange: (updater) => {
            const next =
                typeof updater === 'function'
                    ? updater(sorting.value)
                    : updater;

            sorting.value = next.slice(-1);
            pagination.value = {...pagination.value, pageIndex: 0};
        },
    });

    async function load(): Promise<void> {
        const current = ++request;
        loading.value = true;
        loadError.value = null;

        try {
            const sort = sorting.value[0];
            const {data} = await sendActionRequest<SearchResponse>(
                'GET',
                props.searchUrl,
                {
                    params: {
                        orderId: props.orderId,
                        search: appliedSearch.value,
                        type: typeFilter.value,
                        sort: sort?.id,
                        direction: sort?.desc ? 'desc' : 'asc',
                        page: pagination.value.pageIndex + 1,
                        perPage: pagination.value.pageSize,
                    },
                }
            );

            if (current !== request) {
                return;
            }

            rows.value = data.data;
            total.value = data.total;
            types.value = data.types;
            priceSortedBy.value = data.priceSortedBy;
        } catch (error: any) {
            if (current === request) {
                loadError.value =
                    error?.response?.data?.message ?? props.labels.loadFailed;
            }
        } finally {
            if (current === request) {
                loading.value = false;
            }
        }
    }

    watch(search, (value) => {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(() => {
            appliedSearch.value = value.trim();
            pagination.value = {...pagination.value, pageIndex: 0};
        }, SEARCH_DELAY);
    });

    watch(typeFilter, () => {
        pagination.value = {...pagination.value, pageIndex: 0};
    });

    watch([appliedSearch, typeFilter, sorting, pagination], () => void load(), {
        immediate: true,
        deep: true,
    });

    onBeforeUnmount(() => clearTimeout(searchTimer));

    async function submit(): Promise<void> {
        if (!canSubmit.value) {
            return;
        }

        const ids = selectedIds.value;

        submitting.value = true;
        submitError.value = null;
        rowErrors.value = {};

        try {
            const {data} = await sendActionRequest<{
                message?: string;
            }>('POST', props.submitUrl, {
                data: {
                    ...props.params,
                    form: 'addLineItem',
                    items: ids.map((id) => ({
                        purchasableId: id,
                        qty: Number(selected.value[id].qty),
                    })),
                },
            });

            if (data.message) {
                displayNotice(data.message);
            }

            emit('added');
            emit('close');
        } catch (error: any) {
            showSubmitError(error?.response?.data ?? {}, ids);
        } finally {
            submitting.value = false;
        }
    }

    function showSubmitError(
        data: {
            message?: string;
            errors?: Record<string, string[]>;
            itemIndex?: number | null;
        },
        ids: number[]
    ): void {
        const errors: Record<number, string> = {};

        for (const [key, messages] of Object.entries(data.errors ?? {})) {
            const match = /^items\.(\d+)\./.exec(key);
            const id = match ? ids[Number(match[1])] : undefined;

            if (id !== undefined && !errors[id]) {
                errors[id] = messages[0];
            }
        }

        if (
            typeof data.itemIndex === 'number' &&
            ids[data.itemIndex] !== undefined
        ) {
            errors[ids[data.itemIndex]] = data.message ?? props.labels.failed;
        }

        rowErrors.value = errors;
        submitError.value = Object.keys(errors).length
            ? props.labels.fixItems
            : data.message ?? props.labels.failed;
    }
</script>

<template>
    <craft-dialog
        open
        :label="labels.addLineItem"
        class="add-line-item"
        @craft-after-hide="emit('close')"
    >
        <div class="add-line-item__body">
            <AdminTable
                :table="table"
                :from="from"
                :to="to"
                :total="total"
                :loading="loading"
                :empty-message="loadError ?? labels.noPurchasables"
                read-only
            >
                <template #table-header>
                    <div class="add-line-item__controls">
                        <craft-input
                            class="add-line-item__search"
                            type="search"
                            :label="labels.search"
                            label-sr-only
                            :placeholder="labels.search"
                            .modelValue="search"
                            @model-value-changed="
                                search = String($event.target.modelValue ?? '')
                            "
                        />
                        <craft-select
                            v-if="types.length > 1"
                            :label="labels.type"
                            label-sr-only
                        >
                            <select
                                slot="input"
                                @change="
                                    typeFilter = (
                                        $event.target as HTMLSelectElement
                                    ).value
                                "
                            >
                                <option value="" :selected="typeFilter === ''">
                                    {{ labels.allTypes }}
                                </option>
                                <option
                                    v-for="type in types"
                                    :key="type.type"
                                    :value="type.type"
                                    :selected="typeFilter === type.type"
                                >
                                    {{ type.label }}
                                </option>
                            </select>
                        </craft-select>
                    </div>
                </template>
            </AdminTable>
            <p v-if="submitError" role="alert" class="add-line-item__error">
                {{ submitError }}
            </p>
        </div>
        <div slot="footer" class="add-line-item__footer">
            <span class="add-line-item__count">
                {{
                    selectedIds.length
                        ? labels.selectedCount.replace(
                              '{count}',
                              String(selectedIds.length)
                          )
                        : ''
                }}
            </span>
            <craft-button type="button" @click="emit('close')">
                {{ labels.cancel }}
            </craft-button>
            <craft-button
                type="button"
                variant="primary"
                :loading="submitting"
                :disabled="!canSubmit || undefined"
                @click="submit"
            >
                {{ labels.add }}
            </craft-button>
        </div>
    </craft-dialog>
</template>

<style scoped>
    .add-line-item {
        --c-dialog-inline-size: min(95vw, 70rem);
        --c-dialog-max-inline-size: min(95vw, 70rem);
    }

    .add-line-item__body {
        display: flex;
        flex-direction: column;
        block-size: min(60vh, 34rem);
    }

    .add-line-item__body :deep(.admin-table) {
        display: flex;
        flex: 1;
        flex-direction: column;
        min-block-size: 0;
    }

    .add-line-item__body :deep(.admin-table__body) {
        flex: 1;
        min-block-size: 0;
        overflow: auto;
    }

    .add-line-item__body :deep(.admin-table__footer) {
        margin-block-start: auto;
    }

    /* Tables strip the border and padding from their inputs so they fill a cell. The quantity box
       is a field in its own right, so it keeps the chrome of any other input. */
    .add-line-item__body :deep(.cp-table input.form-control) {
        border-width: var(
            --c-input-border-width,
            var(--c-form-control-border-width)
        );
        border-style: var(
            --c-input-border-style,
            var(--c-form-control-border-style)
        );
        border-color: var(
            --c-input-border-color,
            var(--c-form-control-border-color)
        );
        padding-block: 0;
        padding-inline: var(--c-input-spacing-inline, 0.5rem);
        height: auto;
    }

    .add-line-item__controls {
        display: flex;
        gap: var(--c-spacing-md, 0.75rem);
        align-items: center;
    }

    .add-line-item__search {
        flex: 1;
    }

    .add-line-item__error {
        color: var(--c-text-danger, #c0392b);
        font-size: 0.875em;
        margin: 0.25rem 0 0;
    }

    .add-line-item__footer {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: var(--c-spacing-md, 0.75rem);
    }

    .add-line-item__count {
        margin-inline-end: auto;
    }
</style>

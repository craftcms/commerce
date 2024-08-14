<template>
    <div>
        <template v-if="!showForm">
            <template v-if="lineItems.length > 0">
                <div class="text-left">
                    <div class="flex">
                        <btn-link
                            @click="showForm = true"
                            button-class="btn icon add"
                            >{{ 'Add a line item' | t('commerce') }}</btn-link
                        >
                        <btn-link
                            @click="addLineItem"
                            button-class="btn icon add"
                            >{{
                                'Add a custom line item' | t('commerce')
                            }}</btn-link
                        >
                    </div>
                </div>
            </template>
            <template v-else>
                <div class="starter">
                    <div data-icon="info"></div>
                    <h2>{{ 'Your order is empty' | t('commerce') }}</h2>

                    <div class="flex">
                        <btn-link
                            @click="showForm = true"
                            button-class="btn icon add"
                            >{{ 'Add a line item' | t('commerce') }}</btn-link
                        >
                        <btn-link
                            @click="addLineItem"
                            button-class="btn icon add"
                            >{{
                                'Add a custom line item' | t('commerce')
                            }}</btn-link
                        >
                    </div>
                </div>
            </template>
        </template>
        <template v-else>
            <hr />
            <div>
                <div class="flex add-line-item-table-header pb">
                    <h2>
                        {{ $options.filters.t('Add a line item', 'commerce') }}
                    </h2>
                    <form
                        @submit.prevent="lineItemAdd()"
                        class="add-line-item-form"
                    >
                        <div class="buttons buttons--add-line-item">
                            <input
                                type="button"
                                class="btn"
                                :class="{disabled: formDisabled}"
                                :disabled="formDisabled"
                                :value="
                                    $options.filters.t('Cancel', 'commerce')
                                "
                                @click="showForm = false"
                            />
                            <input
                                type="submit"
                                class="btn secondary"
                                :class="{disabled: submitDisabled}"
                                :disabled="submitDisabled"
                                :value="$options.filters.t('Add', 'commerce')"
                                @click.prevent="lineItemAdd()"
                            />
                        </div>
                    </form>
                </div>
                <admin-table
                    ref="addAdminTable"
                    :allow-multiple-selections="true"
                    :table-data-endpoint="endpoint"
                    :checkboxes="true"
                    :checkbox-status="isCheckboxEnabled"
                    :columns="purchasableTableColumns"
                    :padded="true"
                    per-page="10"
                    search="true"
                    @onSelect="handleCheckboxSelect"
                    @data="handleTableData"
                ></admin-table>

                <form
                    @submit.prevent="lineItemAdd()"
                    class="add-line-item-form"
                >
                    <div class="buttons buttons--add-line-item">
                        <input
                            type="button"
                            class="btn"
                            :class="{disabled: formDisabled}"
                            :disabled="formDisabled"
                            :value="$options.filters.t('Cancel', 'commerce')"
                            @click="showForm = false"
                        />
                        <input
                            type="submit"
                            class="btn secondary"
                            :class="{disabled: submitDisabled}"
                            :disabled="submitDisabled"
                            :value="$options.filters.t('Add', 'commerce')"
                            @click.prevent="lineItemAdd()"
                        />
                    </div>
                </form>
            </div>
        </template>
    </div>
</template>

<script>
    import {mapActions, mapGetters, mapState} from 'vuex';
    import _find from 'lodash.find';
    import AdminTable from '@craftcms/vue/admintable/App';

    export default {
        components: {
            AdminTable,
        },

        data() {
            return {
                showForm: false,
                selectedPurchasables: [],
                currentTableData: null,
                purchasableTableColumns: [
                    {
                        name: 'description',
                        title: this.$options.filters.t(
                            'Description',
                            'commerce'
                        ),
                        sortField: 'description',
                    },
                    {
                        name: 'sku',
                        title: this.$options.filters.t('SKU', 'commerce'),
                        sortField: 'sku',
                    },
                    {
                        name: 'priceAsCurrency',
                        title: this.$options.filters.t('Price', 'commerce'),
                        sortField: 'price',
                    },
                    {
                        name: 'isAvailable',
                        title: this.$options.filters.t(
                            'Available?',
                            'commerce'
                        ),
                        callback: function (value) {
                            if (value) {
                                return '<span data-icon="check" title=""></span>';
                            }
                        },
                    },
                    {
                        name: '__component:qty-input',
                        title: this.$options.filters.t('Qty', 'commerce'),
                    },
                    {
                        name: '__slot:detail',
                        title: '',
                        titleClass: 'thin',
                        callback: function (value) {
                            if (
                                value &&
                                (Object.keys(value).length || value.length)
                            ) {
                                return '<textarea>' + value + '</textarea>';
                            }
                        },
                    },
                ],
            };
        },

        computed: {
            ...mapState({
                purchasables: (state) => state.purchasables,
                draft: (state) => state.draft,
            }),

            ...mapGetters([
                'canAddLineItem',
                'defaultShippingCategoryId',
                'defaultTaxCategoryId',
                'getErrors',
                'lineItemTypes',
                'orderId',
            ]),

            formDisabled() {
                return !this.canAddLineItem;
            },

            submitDisabled() {
                if (!this.canAddLineItem || !this.selectedPurchasables.length) {
                    return true;
                }

                return false;
            },

            lineItems() {
                return this.$store.state.draft.order.lineItems;
            },

            endpoint() {
                let endpoint =
                    'commerce/orders/purchasables-table?siteId=' +
                    this.draft.order.orderSiteId;

                if (this.draft.order.customerId) {
                    endpoint =
                        endpoint + '&customerId=' + this.draft.order.customerId;
                }

                return endpoint;
            },
        },

        methods: {
            ...mapActions(['displayError']),

            lineItemAdd() {
                if (!this.canAddLineItem) {
                    this.displayError(
                        this.$options.filters.t(
                            'You are not allowed to add a line item.',
                            'commerce'
                        )
                    );
                    return;
                }

                this.addLineItem();
            },

            addLineItem() {
                let lineItems = [];
                if (this.selectedPurchasables.length) {
                    for (let i = 0; i < this.selectedPurchasables.length; i++) {
                        let purchasable = this.selectedPurchasables[i];
                        if (purchasable && purchasable.isAvailable) {
                            lineItems.push({
                                id: null,
                                type: this.lineItemTypes.Purchasable,
                                lineItemStatusId: null,
                                price: purchasable.price,
                                promotionalPrice: purchasable.promotionalPrice,
                                qty: purchasable.qty,
                                note: '',
                                privateNote: '',
                                orderId: this.orderId,
                                purchasableId: purchasable.id,
                                sku: purchasable.sku,
                                options: [],
                                adjustments: [],
                                isNew: true,
                                uid: purchasable.newLineItemUid,
                            });
                            this.$store.commit(
                                'updateRecentlyAddedLineItems',
                                purchasable.id +
                                    '-' +
                                    purchasable.newLineItemOptionsSignature
                            );
                        }
                    }
                } else {
                    // If we aren't adding a purchasable line item we need to add a custom one
                    var lut = [];
                    for (var i = 0; i < 256; i++) {
                        lut[i] = (i < 16 ? '0' : '') + i.toString(16);
                    }
                    var d0 = (Math.random() * 0xffffffff) | 0;
                    var d1 = (Math.random() * 0xffffffff) | 0;
                    var d2 = (Math.random() * 0xffffffff) | 0;
                    var d3 = (Math.random() * 0xffffffff) | 0;
                    const UUID =
                        lut[d0 & 0xff] +
                        lut[(d0 >> 8) & 0xff] +
                        lut[(d0 >> 16) & 0xff] +
                        lut[(d0 >> 24) & 0xff] +
                        '-' +
                        lut[d1 & 0xff] +
                        lut[(d1 >> 8) & 0xff] +
                        '-' +
                        lut[((d1 >> 16) & 0x0f) | 0x40] +
                        lut[(d1 >> 24) & 0xff] +
                        '-' +
                        lut[(d2 & 0x3f) | 0x80] +
                        lut[(d2 >> 8) & 0xff] +
                        '-' +
                        lut[(d2 >> 16) & 0xff] +
                        lut[(d2 >> 24) & 0xff] +
                        lut[d3 & 0xff] +
                        lut[(d3 >> 8) & 0xff] +
                        lut[(d3 >> 16) & 0xff] +
                        lut[(d3 >> 24) & 0xff];

                    const newLineItem = {
                        adjustments: [],
                        description: Craft.t('commerce', 'Custom'),
                        id: null,
                        isNew: true,
                        lineItemStatusId: null,
                        note: '',
                        options: [],
                        orderId: this.orderId,
                        price: 0,
                        privateNote: '',
                        promotionalPrice: null,
                        purchasableId: null,
                        qty: 1,
                        sku: Math.random().toString(36).substring(2, 8), // Random SKU
                        shippingCategoryId: this.defaultShippingCategoryId,
                        type: this.lineItemTypes.Custom,
                        taxCategoryId: this.defaultTaxCategoryId,
                        uid: UUID,
                    };

                    lineItems.push(newLineItem);
                }

                if (lineItems.length) {
                    this.$emit('addLineItem', lineItems);
                }

                this.selectedPurchasables = [];
                this.showForm = false;
            },

            isCheckboxEnabled(row) {
                return row.isAvailable;
            },

            handleCheckboxSelect(ids) {
                if (ids && ids.length) {
                    let $this = this;
                    this.selectedPurchasables = ids.map((id) => {
                        for (
                            let i = 0;
                            i < $this.currentTableData.length;
                            i++
                        ) {
                            if ($this.currentTableData[i].id == id) {
                                if ($this.currentTableData[i].qty === '') {
                                    $this.currentTableData[i].qty = '1';
                                }

                                return $this.currentTableData[i];
                            }
                        }

                        return false;
                    });
                } else {
                    this.selectedPurchasables = [];
                }
            },

            handleTableData(data) {
                this.currentTableData = data;
            },
        },

        watch: {
            currentTableData: {
                deep: true,

                handler(newVal, oldVal) {
                    if (!newVal || !oldVal) {
                        return;
                    }

                    let selectedPurchasableIds = this.selectedPurchasables.map(
                        (p) => p.id
                    );

                    for (let i = 0; i < newVal.length; i++) {
                        let index = selectedPurchasableIds.indexOf(
                            newVal[i].id
                        );
                        if (!newVal[i].qty && index >= 0) {
                            // Remove the select if there is one
                            this.$refs.addAdminTable.removeCheck(newVal[i].id);
                            // haveIdsChanged = true;
                        } else if (newVal[i].qty && index < 0) {
                            // Add selected if we have a qty
                            this.$refs.addAdminTable.addCheck(newVal[i].id);
                            // selectedPurchasableIds.push(newVal[i].id);
                            // haveIdsChanged = true;
                        }
                    }
                },
            },
        },
    };
</script>

<style lang="scss">
    @import 'craftcms-sass/mixins';

    /* Starter */

    .starter {
        text-align: center;

        div[data-icon] {
            font-size: 4em;
            color: #f1f5f8;
        }
    }

    /* Add line item form */

    .add-line-item-form {
        max-width: 100%;
    }

    .add-line-item-table-header {
        justify-content: space-between;
        align-items: center;

        h2 {
            margin: 0;
        }
    }

    /* Purchasable select option */

    .purchasable-select-option {
        display: flex;

        .description {
            flex-grow: 1;
        }

        .sku {
            color: $lightTextColor;
            @include margin-right(20px);
        }

        &.notAvailable {
            .description {
                color: $lightTextColor;
            }
            .price {
                color: red;
            }
        }

        .price {
            width: 10%;
            text-align: right;
        }
    }

    .buttons--add-line-item {
        justify-content: flex-end;
    }
</style>

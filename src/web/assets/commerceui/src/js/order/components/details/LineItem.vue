<template>
    <div class="line-item">
        <div
            class="absolute line-item-bg"
            :class="{
                'highlight-line-item': highlightLineItem,
                'error-bg': hasLineItemErrors(lineItemKey),
            }"
        ></div>
        <div class="relative">
            <order-block class="order-flex order-box-sizing">
                <div class="w-1/4">
                    <!-- Description -->
                    <div
                        v-if="
                            editing &&
                            editMode &&
                            lineItem.type &&
                            lineItem.type.value === lineItemTypes.Custom.value
                        "
                    >
                        <field
                            :label="
                                $options.filters.t('Description', 'commerce')
                            "
                            v-slot:default="slotProps"
                        >
                            <input
                                :id="slotProps.id"
                                type="text"
                                class="text"
                                size="10"
                                v-model="description"
                                :class="{
                                    error: getErrors(
                                        'lineItems.' +
                                            lineItemKey +
                                            '.description'
                                    ).length,
                                }"
                            />
                        </field>
                    </div>
                    <order-title v-else>
                        <a
                            :href="lineItem.purchasableCpEditUrl"
                            v-if="lineItem.purchasableCpEditUrl"
                        >
                            {{ lineItem.description }}
                        </a>
                        <span v-else>
                            {{ lineItem.description }}
                        </span>
                    </order-title>
                    <!-- SKU -->
                    <div
                        :class="{
                            'mt-s':
                                editing &&
                                editMode &&
                                lineItem.type &&
                                lineItem.type.value ===
                                    lineItemTypes.Custom.value,
                        }"
                    >
                        <template
                            v-if="
                                editing &&
                                editMode &&
                                lineItem.type &&
                                lineItem.type.value ===
                                    lineItemTypes.Custom.value
                            "
                        >
                            <field
                                :label="$options.filters.t('SKU', 'commerce')"
                                v-slot:default="slotProps"
                            >
                                <input
                                    :id="slotProps.id"
                                    type="text"
                                    class="text"
                                    size="4"
                                    v-model="sku"
                                    :class="{
                                        error: getErrors(
                                            'lineItems.' + lineItemKey + '.sku'
                                        ).length,
                                    }"
                                />
                            </field>
                        </template>
                        <code class="extralight" v-else>{{
                            lineItem.sku
                        }}</code>
                    </div>

                    <!-- Status -->
                    <div class="my-1">
                        <line-item-status
                            :line-item="lineItem"
                            :editing="editing && editMode"
                            @change="updateLineItemStatusId"
                        ></line-item-status>
                    </div>

                    <!-- Edit-->
                    <div v-if="canEdit">
                        <btn-link v-if="!editMode" @click="enableEditMode()">{{
                            'Edit' | t('commerce')
                        }}</btn-link>
                    </div>

                    <!-- Snapshots-->
                    <div>
                        <btn-link @click="openSnapshotModal()">{{
                            'Snapshots' | t('commerce')
                        }}</btn-link>
                    </div>
                    <!-- Edit-->
                    <div
                        v-if="
                            canEdit &&
                            (totalCommittedStock === 0 ||
                                lineItem.fulfilledTotalQuantity < 1)
                        "
                    >
                        <btn-link
                            button-class="btn-link btn-link--danger"
                            @click="removeLineItem"
                            >{{ 'Remove' | t('commerce') }}</btn-link
                        >
                    </div>
                </div>
                <div class="w-3/4">
                    <div class="order-flex pb">
                        <ul class="line-item-section line-item-price">
                            <li class="order-flex order-flex-wrap">
                                <div class="order-flex">
                                    <template
                                        v-if="
                                            editing &&
                                            editMode &&
                                            (recalculationMode === 'none' ||
                                                lineItem.type.value ===
                                                    lineItemTypes.Custom.value)
                                        "
                                    >
                                        <field
                                            :label="
                                                $options.filters.t(
                                                    'Promotional Price',
                                                    'commerce'
                                                )
                                            "
                                            v-slot:default="slotProps"
                                        >
                                            <input
                                                :id="slotProps.id"
                                                type="text"
                                                class="text"
                                                size="10"
                                                v-model="promotionalPrice"
                                                :class="{
                                                    error: getErrors(
                                                        'lineItems.' +
                                                            lineItemKey +
                                                            '.promotionalPrice'
                                                    ).length,
                                                }"
                                                ref="promotionalPrice"
                                            />
                                        </field>
                                    </template>
                                    <template v-else>
                                        <label class="light" for="salePrice">{{
                                            'Sale Price' | t('commerce')
                                        }}</label>
                                        <div>
                                            {{ lineItem.salePriceAsCurrency }}
                                        </div>
                                    </template>
                                    <template
                                        v-if="
                                            editing &&
                                            editMode &&
                                            (recalculationMode === 'none' ||
                                                lineItem.type.value ===
                                                    lineItemTypes.Custom.value)
                                        "
                                    >
                                        <div>
                                            <field
                                                :label="
                                                    $options.filters.t(
                                                        'Price',
                                                        'commerce'
                                                    )
                                                "
                                                v-slot:default="slotProps"
                                            >
                                                <input
                                                    :id="slotProps.id"
                                                    type="text"
                                                    class="text"
                                                    size="10"
                                                    v-model="price"
                                                    :class="{
                                                        error: getErrors(
                                                            'lineItems.' +
                                                                lineItemKey +
                                                                '.price'
                                                        ).length,
                                                    }"
                                                    ref="price"
                                                />
                                            </field>
                                        </div>
                                    </template>
                                </div>
                            </li>
                            <template v-if="lineItem.onPromotion">
                                <li>
                                    <span class="light">{{
                                        'Original Price' | t('commerce')
                                    }}</span
                                    >&nbsp;<del>{{
                                        lineItem.priceAsCurrency
                                    }}</del>
                                </li>
                                <li>
                                    <span class="light">{{
                                        'Promotional Amount' | t('commerce')
                                    }}</span>
                                    {{ lineItem.promotionalAmountAsCurrency }}
                                </li>
                            </template>
                        </ul>
                        <div class="line-item-section">
                            <div class="order-flex">
                                <template v-if="editing && editMode">
                                    <field
                                        :label="
                                            $options.filters.t(
                                                'Quantity',
                                                'commerce'
                                            )
                                        "
                                        v-slot:default="slotProps"
                                    >
                                        <input
                                            :id="slotProps.id"
                                            type="text"
                                            class="text"
                                            size="3"
                                            v-model="qty"
                                            :class="{
                                                error: getErrors(
                                                    'lineItems.' +
                                                        lineItemKey +
                                                        '.qty'
                                                ).length,
                                            }"
                                        />
                                    </field>
                                </template>
                                <template v-else>
                                    <span>{{ lineItem.qty }}</span>
                                </template>
                            </div>
                        </div>
                        <div class="order-flex-grow text-right">
                            {{ lineItem.subtotalAsCurrency }}
                        </div>
                    </div>

                    <div>
                        <order-block
                            class="order-flex"
                            v-if="
                                lineItem.type.value ===
                                lineItemTypes.Custom.value
                            "
                        >
                            <line-item-property
                                :editing="editing && editMode"
                                :line-item="lineItem"
                                :attribute="'hasFreeShipping'"
                                :label="
                                    $options.filters.t(
                                        'Has Free Shipping',
                                        'commerce'
                                    )
                                "
                                :classes="{'order-flex': true}"
                                @updateLineItem="
                                    $emit('updateLineItem', $event)
                                "
                            />
                        </order-block>
                        <order-block
                            class="order-flex"
                            v-if="
                                lineItem.type.value ===
                                lineItemTypes.Custom.value
                            "
                        >
                            <line-item-property
                                :editing="editing && editMode"
                                :line-item="lineItem"
                                :attribute="'isShippable'"
                                :label="
                                    $options.filters.t(
                                        'Is Shippable',
                                        'commerce'
                                    )
                                "
                                classes="order-flex line-item-no-margin"
                                @updateLineItem="
                                    $emit('updateLineItem', $event)
                                "
                            >
                                <field
                                    :label="
                                        fieldLabel(
                                            $options.filters.t(
                                                'Shipping Category',
                                                'commerce'
                                            )
                                        )
                                    "
                                    style="
                                        margin-top: 0;
                                        margin-left: auto;
                                        width: 60%;
                                    "
                                    classes="order-flex"
                                    input-class="flex-grow"
                                >
                                    <template v-if="editing && editMode">
                                        <select-input
                                            label="name"
                                            :options="shippingCategoryOptions"
                                            :filterable="true"
                                            :placeholder="shippingCategory"
                                            v-model="shippingCategoryId"
                                        />
                                    </template>
                                    <template v-else>
                                        {{ shippingCategory }}
                                    </template>
                                </field>
                            </line-item-property>
                        </order-block>
                        <order-block
                            class="order-flex"
                            v-if="
                                lineItem.type.value ===
                                lineItemTypes.Custom.value
                            "
                        >
                            <line-item-property
                                :editing="editing && editMode"
                                :line-item="lineItem"
                                :attribute="'isPromotable'"
                                :label="
                                    $options.filters.t(
                                        'Is Promotable',
                                        'commerce'
                                    )
                                "
                                :classes="{'order-flex': true}"
                                @updateLineItem="
                                    $emit('updateLineItem', $event)
                                "
                            />
                        </order-block>
                        <order-block
                            class="order-flex"
                            v-if="
                                lineItem.type.value ===
                                lineItemTypes.Custom.value
                            "
                        >
                            <line-item-property
                                :editing="editing && editMode"
                                :line-item="lineItem"
                                :attribute="'isTaxable'"
                                :label="
                                    $options.filters.t('Is Taxable', 'commerce')
                                "
                                classes="order-flex line-item-no-margin"
                                @updateLineItem="
                                    $emit('updateLineItem', $event)
                                "
                            >
                                <field
                                    :label="
                                        fieldLabel(
                                            $options.filters.t(
                                                'Tax Category',
                                                'commerce'
                                            )
                                        )
                                    "
                                    style="
                                        margin-top: 0;
                                        margin-left: auto;
                                        width: 60%;
                                    "
                                    classes="order-flex"
                                    input-class="flex-grow"
                                >
                                    <template v-if="editing && editMode">
                                        <select-input
                                            label="name"
                                            :options="taxCategoryOptions"
                                            :filterable="true"
                                            :placeholder="taxCategory"
                                            v-model="taxCategoryId"
                                        />
                                    </template>
                                    <template v-else>
                                        {{ taxCategory }}
                                    </template>
                                </field>
                            </line-item-property>
                        </order-block>
                        <line-item-adjustments
                            :order-id="orderId"
                            :line-item="lineItem"
                            :editing="editing && editMode"
                            :recalculation-mode="recalculationMode"
                            :errorPrefix="
                                'lineItems.' + lineItemKey + '.adjustments.'
                            "
                            @updateLineItem="$emit('updateLineItem', $event)"
                        ></line-item-adjustments>
                        <line-item-options
                            :line-item="lineItem"
                            :editing="editing && editMode"
                            @updateLineItem="$emit('updateLineItem', $event)"
                        ></line-item-options>
                        <line-item-notes
                            :line-item="lineItem"
                            :editing="editing && editMode"
                            @updateLineItem="$emit('updateLineItem', $event)"
                        ></line-item-notes>

                        <order-block class="text-right">
                            <div>
                                <strong>{{ lineItem.totalAsCurrency }}</strong>
                            </div>
                        </order-block>
                    </div>
                </div>
            </order-block>
            <div
                class="line-item-buttons pb text-right"
                v-if="editing && editMode"
            >
                <div class="buttons right">
                    <btn-link button-class="btn" @click="cancelEdit">{{
                        $options.filters.t('Cancel', 'commerce')
                    }}</btn-link>
                    <btn-link button-class="btn secondary" @click="applyEdit">{{
                        $options.filters.t('Done', 'commerce')
                    }}</btn-link>
                </div>
            </div>

            <div class="hidden">
                <div ref="snapshots" class="order-edit-modal modal fitted">
                    <div class="body">
                        <h2>{{ lineItem.description }}</h2>
                        <h3>{{ 'Line Item' | t('commerce') }}</h3>
                        <snapshot :show="true">{{
                            lineItem.snapshot
                        }}</snapshot>
                        <hr />
                        <h3 v-if="lineItem.adjustments.length">
                            {{ 'Adjustments' | t('commerce') }}
                        </h3>
                        <template
                            v-for="(adjustment, key) in lineItem.adjustments"
                        >
                            <div :key="key">
                                <h4 class="m-0">
                                    {{ adjustment.name
                                    }}<span v-if="adjustment.description">
                                        - {{ adjustment.description }}</span
                                    >
                                </h4>
                                <h5 class="adjustment-type mt-tiny">
                                    {{ adjustment.type }}
                                </h5>
                                <snapshot :show="true">{{
                                    adjustment.sourceSnapshot
                                }}</snapshot>
                                <hr />
                            </div>
                        </template>
                    </div>
                    <div class="footer">
                        <div class="buttons right">
                            <btn-link
                                button-class="btn"
                                @click="closeSnapshotModal()"
                                >{{
                                    $options.filters.t('Close', 'commerce')
                                }}</btn-link
                            >
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
    /* global Garnish */

    import {mapActions, mapGetters, mapState} from 'vuex';
    import debounce from 'lodash.debounce';
    import Field from '../../../base/components/Field';
    import LineItemStatus from './LineItemStatus';
    import LineItemOptions from './LineItemOptions';
    import LineItemNotes from './LineItemNotes';
    import LineItemAdjustments from './LineItemAdjustments';
    import LineItemProperty from './LineItemProperty.vue';
    import Snapshot from './Snapshot';
    import SelectInput from '../../../base/components/SelectInput';

    export default {
        components: {
            Field,
            LineItemStatus,
            LineItemOptions,
            LineItemNotes,
            LineItemAdjustments,
            LineItemProperty,
            SelectInput,
            Snapshot,
        },

        props: {
            lineItem: {
                type: Object,
            },
            lineItemKey: {
                type: Number,
            },
            recalculationMode: {
                type: String,
            },
            editing: {
                type: Boolean,
            },
        },

        data() {
            return {
                editMode: false,
                highlight: false,
                maskOptions: {
                    alias: 'currency',
                    autoGroup: false,
                    clearMaskOnLostFocus: false,
                    digits: 2,
                    digitsOptional: false,
                    groupSeparator: ',',
                    placeholder: '0',
                    prefix: '',
                    radixPoint: '.',
                },
                modal: {
                    ref: 'snapshots',
                    modal: null,
                    isVisible: false,
                },
                originalLineItem: null,
                priceInput: null,
                promotionalPriceInput: null,
            };
        },

        computed: {
            ...mapState({
                draft: (state) => state.draft,
                recentlyAddedLineItems: (state) => state.recentlyAddedLineItems,
            }),

            ...mapGetters([
                'currencyConfig',
                'canEdit',
                'getErrors',
                'hasLineItemErrors',
                'lineItemTypes',
                'orderId',
                'shippingCategories',
                'taxCategories',
                'totalCommittedStock',
            ]),

            description: {
                get() {
                    return this.lineItem.description;
                },
                set: debounce(function (val) {
                    const lineItem = this.lineItem;
                    lineItem.description = val;
                    this.$emit('updateLineItem', lineItem);
                }, 1000),
            },

            sku: {
                get() {
                    return this.lineItem.sku;
                },
                set: debounce(function (val) {
                    const lineItem = this.lineItem;
                    lineItem.sku = val;
                    this.$emit('updateLineItem', lineItem);
                }, 1000),
            },

            shippingCategoryId: {
                get() {
                    return {
                        name: this.shippingCategories[
                            this.lineItem.shippingCategoryId
                        ],
                        value: this.lineItem.shippingCategoryId,
                    };
                },
                set: debounce(function (shippingCategoryOption) {
                    const lineItem = this.lineItem;
                    lineItem.shippingCategoryId = shippingCategoryOption.value;
                    this.$emit('updateLineItem', lineItem);
                }, 1000),
            },

            taxCategoryId: {
                get() {
                    return {
                        name: this.taxCategories[this.lineItem.taxCategoryId],
                        value: this.lineItem.taxCategoryId,
                    };
                },
                set: debounce(function (taxCategoryOption) {
                    const lineItem = this.lineItem;
                    lineItem.taxCategoryId = taxCategoryOption.value;
                    this.$emit('updateLineItem', lineItem);
                }, 1000),
            },

            promotionalPrice: {
                get() {
                    return this.lineItem.promotionalPrice;
                },
            },

            price: {
                get() {
                    return this.lineItem.price;
                },
                set: debounce(function (val) {
                    const lineItem = this.lineItem;
                    lineItem.price = val;
                    this.$emit('updateLineItem', lineItem);
                }, 1000),
            },

            qty: {
                get() {
                    return this.lineItem.qty;
                },
                set: debounce(function (val) {
                    if (val !== '') {
                        const lineItem = this.lineItem;
                        lineItem.qty = val;
                        this.$emit('updateLineItem', lineItem);
                    }
                }, 1000),
            },

            shippingCategory() {
                if (!this.lineItem.shippingCategoryId) {
                    return null;
                }

                if (
                    typeof this.shippingCategories[
                        this.lineItem.shippingCategoryId
                    ] === 'undefined'
                ) {
                    return this.lineItem.shippingCategoryId;
                }

                return this.shippingCategories[
                    this.lineItem.shippingCategoryId
                ];
            },

            shippingCategoryOptions() {
                return Object.keys(this.shippingCategories).map((id) => {
                    return {
                        name: this.shippingCategories[id],
                        value: id,
                    };
                });
            },

            taxCategoryOptions() {
                return Object.keys(this.taxCategories).map((id) => {
                    return {
                        name: this.taxCategories[id],
                        value: id,
                    };
                });
            },

            taxCategory() {
                if (!this.lineItem.taxCategoryId) {
                    return null;
                }

                if (
                    typeof this.taxCategories[this.lineItem.taxCategoryId] ===
                    'undefined'
                ) {
                    return this.lineItem.taxCategoryId;
                }

                return this.taxCategories[this.lineItem.taxCategoryId];
            },

            highlightLineItem() {
                return (
                    this.lineItem &&
                    this.recentlyAddedLineItems &&
                    this.recentlyAddedLineItems.length &&
                    this.recentlyAddedLineItems.indexOf(
                        this.lineItem.purchasableId +
                            '-' +
                            this.lineItem.optionsSignature
                    ) >= 0
                );
            },
        },

        methods: {
            ...mapActions(['edit']),

            enableEditMode() {
                this.highlight = false;
                this.editMode = true;
                this.originalLineItem = Object.assign({}, this.lineItem);
                this.edit();
            },

            applyEdit() {
                this.originalLineItem = null;
                this.editMode = false;
            },

            cancelEdit() {
                this.editMode = false;
                let lineItem = this.originalLineItem;
                this.$emit('updateLineItem', lineItem);
                this.originalLineItem = null;
            },

            _initSnapshotModal() {
                if (!this.modal.modal) {
                    let $this = this;

                    this.modal.modal = new Garnish.Modal(
                        this.$refs[this.modal.ref],
                        {
                            autoShow: false,
                            resizable: false,
                            onHide() {
                                $this.onHideSnapshotModal();
                            },
                        }
                    );
                }
            },

            openSnapshotModal() {
                this._initSnapshotModal();

                if (!this.modal.isVisible) {
                    this.modal.isVisible = true;
                    this.modal.modal.show();
                }
            },

            closeSnapshotModal() {
                this._initSnapshotModal();

                if (this.modal.isVisible) {
                    this.modal.modal.hide();
                }
            },

            onHideSnapshotModal() {
                this.modal.isVisible = false;
            },

            removeLineItem() {
                this.$emit('removeLineItem');
                this.edit();
            },

            updateLineItemStatusId(lineItemStatusId) {
                const lineItem = this.lineItem;
                lineItem.lineItemStatusId = lineItemStatusId;
                this.$emit('updateLineItem', lineItem);
            },

            onPriceChange: debounce(function () {
                const lineItem = this.lineItem;
                let price = this.priceInput.val();
                if (price === '') {
                    price = null;
                }

                lineItem.price = price;
                this.$emit('updateLineItem', lineItem);
            }, 1000),

            onPromotionalPriceChange: debounce(function () {
                const lineItem = this.lineItem;
                let promotionalPrice = this.promotionalPriceInput.val();
                if (promotionalPrice === '') {
                    promotionalPrice = null;
                }

                lineItem.promotionalPrice = promotionalPrice;
                this.$emit('updateLineItem', lineItem);
            }, 1000),

            initPriceInputs() {
                if (
                    this.promotionalPriceInput === null &&
                    this.$refs.promotionalPrice
                ) {
                    this.promotionalPriceInput = $(this.$refs.promotionalPrice);
                    this.promotionalPriceInput.on(
                        'keyup',
                        this.onPromotionalPriceChange
                    );

                    // Make sure mask is cleared when input is empty
                    this.promotionalPriceInput.inputmask({
                        ...this.maskOptions,
                        ...{nullable: true, clearMaskOnLostFocus: true},
                    });
                }

                if (this.priceInput === null && this.$refs.price) {
                    this.priceInput = $(this.$refs.price);
                    this.priceInput.on('keyup', this.onPriceChange);

                    this.priceInput.inputmask(this.maskOptions);
                }
            },

            fieldLabel(label) {
                if (document.querySelector('body').dir === 'rtl') {
                    return ':' + label;
                }

                return label + ':';
            },
        },

        watch: {
            editMode(val) {
                if (val) {
                    this.$nextTick(() => {
                        this.initPriceInputs();
                    });
                }
            },
        },

        mounted() {
            // Setup mask settings passed from the controller
            this.maskOptions.digits = this.currencyConfig.decimals;
            this.maskOptions.groupSeparator =
                this.currencyConfig.groupSeparator;
            this.maskOptions.radixPoint = this.currencyConfig.decimalSeparator;

            this.$nextTick(() => {
                this.initPriceInputs();
            });
        },
    };
</script>

<style lang="scss">
    @import 'craftcms-sass/mixins';

    .line-item {
        transition: background-color 0.5s ease;
        position: relative;

        &-bg {
            transition: background-color 0.5s ease;
            height: 100%;
            top: 0;
            left: -24px;
            right: -24px;

            &.highlight-line-item {
                background: #fffff0;
            }
        }

        &-section {
            width: 20%;
        }

        &-price {
            width: 60%;
        }

        &-buttons::after {
            content: '.';
            display: block;
            height: 0;
            clear: both;
            visibility: hidden;
        }

        &-no-margin {
            margin: 0;
        }

        label {
            @include padding-right(10px);
            max-width: 100%;
        }
    }
</style>

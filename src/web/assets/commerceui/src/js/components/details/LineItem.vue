<template>
    <div class="line-item" :class="{'new-line-item': isLineItemNew}">
        <order-block class="order-flex">
            <div class="w-1/3">
                <!-- Description -->
                <order-title>{{ lineItem.description }}</order-title>

                <!-- SKU -->
                <p><code>{{ lineItem.sku }}</code></p>

                <!-- Status -->
                <line-item-status :line-item="lineItem" :editing="editing" @change="updateLineItemStatusId"></line-item-status>
            </div>
            <div class="w-2/3 order-flex">
                <div class="order-flex-grow">
                    <ul>
                        <li class="order-flex">
                            <template v-if="editing && recalculationMode === 'none'">
                                <field :label="$options.filters.t('Sale Price', 'commerce')" :errors="getErrors('order.lineItems.'+lineItemKey+'.salePrice')" v-slot:default="slotProps">
                                    <input :id="slotProps.id" type="text" class="text" size="10" v-model="salePrice" />
                                </field>
                            </template>
                            <template v-else>
                                <label class="light" for="salePrice">{{"Sale Price"|t('commerce')}}</label>
                                <div>{{ lineItem.salePriceAsCurrency }}</div>
                            </template>
                        </li>
                        <template v-if="lineItem.onSale">
                            <li><span class="light">{{"Original Price"|t('commerce')}}</span>&nbsp;<strike>{{ lineItem.priceAsCurrency }}</strike></li>
                            <li><span class="light">{{"Sale Amount Off"|t('commerce')}}</span> {{ lineItem.saleAmountAsCurrency }}</li>
                        </template>
                    </ul>
                </div>
                <div class="order-flex-grow">
                    <div class="order-flex">
                        <template v-if="!editing">
                            <label class="light" for="quantity">{{"Quantity"|t('commerce')}}</label>
                            <span>{{ lineItem.qty }}</span>
                        </template>
                        <template v-else>
                            <field :label="$options.filters.t('Quantity', 'commerce')" :errors="getErrors('order.lineItems.'+lineItemKey+'.qty')" v-slot:default="slotProps">
                                <input :id="slotProps.id" type="text" class="text" size="3" v-model="qty" />
                            </field>
                        </template>
                    </div>
                </div>
                <div class="order-flex-grow text-right">
                    {{lineItem.subtotalAsCurrency}}
                </div>
            </div>
        </order-block>

        <!-- Shipping & Tax -->
        <order-block class="order-flex">
            <div class="w-1/3">
                <h3 class="light">{{"Shipping & Taxes"|t('commerce')}}</h3>
            </div>
            <div class="w-2/3">
                <small>
                    <ul>
                        <li>
                            {{shippingCategory}} <span class="light"><small>({{"Shipping"|t('commerce')}})</small></span>
                            <input-error :error-key="'order.lineItems.'+lineItemKey+'.shippingCategoryId'"></input-error>
                        </li>
                        <li>{{taxCategory}} <span class="light">({{"Tax"|t('commerce')}})</span></li>
                    </ul>
                </small>

                <!-- Snapshot -->
                <snapshot>{{lineItem.snapshot}}</snapshot>
            </div>
        </order-block>

        <line-item-options :line-item="lineItem" :editing="editing" @updateLineItem="$emit('updateLineItem', $event)"></line-item-options>
        <line-item-notes :line-item="lineItem" :editing="editing" @updateLineItem="$emit('updateLineItem', $event)"></line-item-notes>
        <line-item-adjustments :order-id="orderId" :line-item="lineItem" :editing="editing" :recalculation-mode="recalculationMode" :errorPrefix="'order.lineItems.'+lineItemKey+'.adjustments.'" @updateLineItem="$emit('updateLineItem', $event)"></line-item-adjustments>

        <order-block class="text-right">
            <div>
                <strong>{{ lineItem.totalAsCurrency }}</strong>
            </div>
            <div v-if="editing && isProEdition">
                <btn-link @click="$emit('removeLineItem')">{{"Remove"|t('commerce')}}</btn-link>
            </div>
        </order-block>
    </div>
</template>

<script>
    import {mapGetters, mapState} from 'vuex'
    import debounce from 'lodash.debounce'
    import InputError from '../InputError'
    import Field from '../Field'
    import LineItemStatus from './LineItemStatus'
    import LineItemOptions from './LineItemOptions'
    import LineItemNotes from './LineItemNotes'
    import LineItemAdjustments from './LineItemAdjustments'
    import Snapshot from './Snapshot'

    export default {
        components: {
            InputError,
            Field,
            LineItemStatus,
            LineItemOptions,
            LineItemNotes,
            LineItemAdjustments,
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

        computed: {
            ...mapState({
                lastPurchasableId: state => state.lastPurchasableId,
            }),

            ...mapGetters([
                'getErrors',
                'shippingCategories',
                'taxCategories',
                'orderId',
                'isProEdition',
            ]),

            salePrice: {
                get() {
                    return this.lineItem.salePrice
                },
                set: debounce(function(val) {
                    const lineItem = this.lineItem
                    lineItem.salePrice = val
                    this.$emit('updateLineItem', lineItem)
                }, 1000)
            },

            qty: {
                get() {
                    return this.lineItem.qty
                },
                set: debounce(function(val) {
                    const lineItem = this.lineItem
                    lineItem.qty = val
                    this.$emit('updateLineItem', lineItem)
                }, 1000)
            },

            shippingCategory() {
                if (!this.lineItem.shippingCategoryId) {
                    return null
                }

                if (typeof this.shippingCategories[this.lineItem.shippingCategoryId] === 'undefined') {
                    return this.lineItem.shippingCategoryId
                }

                return this.shippingCategories[this.lineItem.shippingCategoryId]
            },

            taxCategory() {
                if (!this.lineItem.taxCategoryId) {
                    return null
                }

                if (typeof this.taxCategories[this.lineItem.taxCategoryId] === 'undefined') {
                    return this.lineItem.taxCategoryId
                }

                return this.taxCategories[this.lineItem.taxCategoryId]
            },

            isLineItemNew() {
                return !this.lineItem.id && this.lastPurchasableId === this.lineItem.purchasableId
            }
        },

        methods: {
            updateLineItemStatusId(lineItemStatusId) {
                const lineItem = this.lineItem
                lineItem.lineItemStatusId = lineItemStatusId
                this.$emit('updateLineItem', lineItem)
            },
        },
    }
</script>

<style lang="scss">
    @import "~craftcms-sass/src/mixins";

    .line-item {
        border-bottom: 2px solid #eee;
        padding-bottom: 20px;
        margin-bottom: 20px;
        transition: background-color 0.5s ease;

        &.new-line-item {
            background: #FFFFF0;
        }

        label {
            @include margin-right(10px);
        }

        .order-block:first-child {
            border-top: 0;
            padding-top: 0;
        }
    }
</style>

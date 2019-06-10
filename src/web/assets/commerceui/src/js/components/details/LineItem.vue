<template>
    <div class="line-item">
        <div class="order-flex">
            <div class="order-row-title">
                <div class="light"><code>{{ lineItem.sku }}</code></div>

                <br />

                <!-- Shipping & Tax -->
                <small>
                    <ul>
                        <li>
                            {{shippingCategory}} <span class="light"><small>(Shipping)</small></span>
                            <input-error :error-key="'order.lineItems.'+lineItemKey+'.shippingCategoryId'"></input-error>
                        </li>
                        <li>{{taxCategory}} <span class="light">(Tax)</span></li>
                    </ul>
                </small>

                <!-- Snapshot -->
                <div>
                    <template v-if="!showSnapshot">
                        <a @click.prevent="showSnapshot = true">Snapshot <i data-icon="downangle"></i></a>
                    </template>
                    <template v-else>
                        <a @click.prevent="showSnapshot = false">Hide snapshot <i data-icon="upangle"></i></a>
                        <div>
                            <pre><code>{{lineItem.snapshot}}</code></pre>
                        </div>
                    </template>
                </div>
            </div>

            <div class="order-flex-grow">
                <div class="order-indented-block">
                    <div class="order-flex">
                        <div class="order-block-title">
                            <!-- Description -->
                            <h3>{{ lineItem.description }}</h3>

                            <!-- Status -->
                            <line-item-status :line-item="lineItem" @change="updateLineItemStatusId"></line-item-status>
                        </div>
                        <div class="order-flex-grow">
                            <ul>
                                <li>
                                    <template v-if="editing && recalculationMode === 'none'">
                                        <field label="Sale Price" :errors="getErrors('order.lineItems.'+lineItemKey+'.salePrice')">
                                            <input type="text" class="text" size="10" v-model="salePrice" />
                                        </field>
                                    </template>
                                    <template v-else>
                                        <label class="light" for="salePrice">Sale Price</label>
                                        {{ lineItem.salePriceAsCurrency }}
                                    </template>
                                </li>
                                <template v-if="lineItem.onSale">
                                    <li><span class="light">Original Price</span>&nbsp;<strike>{{ lineItem.priceAsCurrency }}</strike></li>
                                    <li><span class="light">Sale Amount Off</span> {{ lineItem.saleAmountAsCurrency }}</li>
                                </template>
                            </ul>

                        </div>
                        <div class="order-flex-grow">
                            <div>
                                <template v-if="!editing">
                                    <label class="light" for="quantity">Quantity</label>
                                    {{ lineItem.qty }}
                                </template>
                                <template v-else>
                                    <field label="Quantity" :errors="getErrors('order.lineItems.'+lineItemKey+'.qty')">
                                        <input type="text" class="text" size="3" v-model="qty" />
                                    </field>
                                </template>
                            </div>
                        </div>
                        <div class="order-flex-grow text-right">
                            {{lineItem.subtotalAsCurrency}}
                        </div>
                    </div>
                </div>

                <line-item-options :line-item="lineItem" :editing="editing" @updateOptions="updateOptions"></line-item-options>

                <template v-if="note || adminNote || editing">
                        <div class="order-indented-block">
                            <div class="order-flex">
                                <div class="order-block-title">
                                    <h3>Note</h3>
                                </div>

                                <div class="order-flex order-flex-grow order-margin-wrapper">
                                    <div class="order-flex-grow order-margin">
                                        <template v-if="!editing">
                                            <template v-if="note">
                                                {{note}}
                                            </template>
                                            <template v-else>
                                                <span class="light">{{ 'No customer note.' }}</span>
                                            </template>
                                        </template>
                                        <template v-else>
                                            <label for="note">Customer Note</label>
                                            <textarea v-model="note" class="text fullwidth"></textarea>
                                        </template>
                                    </div>
                                    <div class="order-flex-grow order-margin">
                                        <template v-if="!editing">
                                            <template v-if="adminNote">
                                                {{adminNote}}
                                            </template>
                                            <template v-else>
                                                <span class="light">{{ 'No admin note.' }}</span>
                                            </template>
                                        </template>
                                        <template v-else>
                                            <label for="note">Admin Note</label>
                                            <textarea v-model="adminNote" class="text fullwidth"></textarea>
                                        </template>
                                    </div>
                                </div>
                            </div>
                        </div>
                </template>

                <template v-if="lineItem.adjustments.length || editing">
                    <div class="order-indented-block">
                        <div class="order-flex">
                            <div class="order-block-title">
                                <h3>Adjustments</h3>
                            </div>

                            <div class="order-flex-grow">
                                <adjustments
                                        :editing="editing"
                                        :error-prefix="'order.lineItems.'+lineItemKey+'.adjustments.'"
                                        :adjustments="adjustments"
                                        :recalculation-mode="recalculationMode"
                                        @addAdjustment="addAdjustment"
                                        @updateAdjustment="updateAdjustment"
                                        @removeAdjustment="removeAdjustment"
                                ></adjustments>
                            </div>
                        </div>
                    </div>
                </template>

                <div class="order-indented-block text-right">
                    <div>
                        <strong>{{ lineItem.totalAsCurrency }}</strong>
                    </div>
                    <div v-if="editing">
                        <a @click.prevent="$emit('removeLineItem')">Remove</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
    import {mapGetters} from 'vuex'
    import {debounce} from 'debounce'
    import InputError from '../InputError'
    import Field from '../Field'
    import Adjustments from './Adjustments'
    import LineItemStatus from './LineItemStatus'
    import LineItemOptions from './LineItemOptions'

    export default {
        components: {
            InputError,
            Field,
            Adjustments,
            LineItemStatus,
            LineItemOptions,
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
                showSnapshot: false,
            }
        },

        computed: {
            ...mapGetters([
                'getErrors',
                'shippingCategories',
                'taxCategories',
                'orderId',
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

            note: {
                get() {
                    return this.lineItem.note
                },
                set: debounce(function(val) {
                    const lineItem = this.lineItem
                    lineItem.note = val
                    this.$emit('updateLineItem', lineItem)
                }, 1000)
            },

            adminNote: {
                get() {
                    return this.lineItem.adminNote
                },
                set: debounce(function(val) {
                    const lineItem = this.lineItem
                    lineItem.adminNote = val
                    this.$emit('updateLineItem', lineItem)
                }, 1000)
            },

            adjustments() {
                return this.lineItem.adjustments
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
        },

        methods: {
            addAdjustment() {
                const adjustment = {
                    id: null,
                    type: 'tax',
                    name: '',
                    description: '',
                    amount: '0.0000',
                    included: '0',
                    orderId: this.orderId,
                    lineItemId: this.lineItem.id
                }

                const lineItem = this.lineItem

                lineItem.adjustments.push(adjustment)

                this.$emit('updateLineItem', lineItem)
            },

            updateAdjustment({adjustment, key}) {
                const lineItem = this.lineItem
                lineItem.adjustments[key] = adjustment
                this.$emit('updateLineItem', lineItem)
            },

            removeAdjustment(key) {
                const lineItem = this.lineItem
                lineItem.adjustments.splice(key, 1)
                this.$emit('updateLineItem', lineItem)
            },

            updateLineItemStatusId(lineItemStatusId) {
                const lineItem = this.lineItem
                lineItem.lineItemStatusId = lineItemStatusId
                this.$emit('updateLineItem', lineItem)
            },

            updateOptions(options) {
                const lineItem = this.lineItem
                lineItem.options = options
                this.$emit('updateLineItem', lineItem)
            },
        },
    }
</script>

<template>
    <div class="line-item">
        <div class="order-flex">
            <div class="order-row-title light">
                <div><code>{{ lineItem.sku }}</code></div>
            </div>

            <div class="order-flex-grow">
                <div class="order-indented-block">
                    <div class="order-flex">
                        <div class="order-block-title">
                            <!-- Description -->
                            <h3>{{ lineItem.description }}</h3>

                            <!-- Status -->
                            <line-item-status :line-item="lineItem"></line-item-status>

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

                            <br />

                            <!-- Snapshot -->
                            <div>
                                <template v-if="!showSnapshot">
                                    <a @click.prevent="showSnapshot = true">Show snapshot</a>
                                </template>
                                <template v-else>
                                    <a @click.prevent="showSnapshot = false">Hide snapshot</a>
                                    <div>
                                        <pre><code>{{lineItem.snapshot}}</code></pre>
                                    </div>
                                </template>
                            </div>
                        </div>
                        <div class="order-flex-grow">
                            <ul>
                                <li>
                                    <label class="light" for="salePrice">Sale Price</label>
                                    <template v-if="$root.editing && $root.draft.order.recalculationMode === 'none'">
                                        <input type="text" class="text" size="10" v-model="lineItem.salePrice" @input="onChange">
                                        <input-error :error-key="'order.lineItems.'+lineItemKey+'.salePrice'"></input-error>
                                    </template>
                                    <template v-else>
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
                                <label class="light" for="quantity">Quantity</label>
                                <template v-if="!$root.editing">
                                    {{ lineItem.qty }}
                                </template>
                                <template v-else>
                                    <input type="text" class="text" size="3" v-model="lineItem.qty" @input="onChange" />
                                    <input-error :error-key="'order.lineItems.'+lineItemKey+'.qty'"></input-error>
                                </template>
                            </div>
                        </div>
                        <div class="order-flex-grow text-right">
                            {{lineItem.subtotalAsCurrency}}
                        </div>
                    </div>
                </div>
                <div class="order-indented-block">
                    <div class="order-flex">
                        <div class="order-block-title">
                            <h3>Options</h3>
                        </div>

                        <div class="order-flex-grow">
                            <template v-if="!$root.editing">
                                <template v-if="Object.keys(lineItem.options).length">
                                    <ul :id="'info-' + lineItem.id">
                                        <template v-for="(option, key) in lineItem.options">
                                            <li :key="'option-'+key">
                                                <code>
                                                    {{key}}:

                                                    <template v-if="Array.isArray(option)">
                                                        <code>{{ option }}</code>
                                                    </template>

                                                    <template v-else>{{ option }}</template>
                                                </code>
                                            </li>
                                        </template>
                                    </ul>
                                </template>
                            </template>
                            <template v-else>
                                <prism-editor v-model="options" language="js" @change="onOptionsChange"></prism-editor>
                            </template>
                        </div>
                    </div>
                </div>
                <div class="order-indented-block">
                    <div class="order-flex">
                        <div class="order-block-title">
                            <h3>Note</h3>
                        </div>

                        <div class="order-flex order-flex-grow order-margin-wrapper">
                            <div class="order-flex-grow order-margin">
                                <template v-if="!$root.editing">
                                    <template v-if="lineItem.note">
                                        {{lineItem.note}}
                                    </template>
                                    <template v-else>
                                        <span class="light">{{ 'No customer note.' }}</span>
                                    </template>
                                </template>
                                <template v-else>
                                    <label for="note">Customer Note</label>
                                    <textarea v-model="lineItem.note" class="text fullwidth" @input="onChange"></textarea>
                                </template>
                            </div>
                            <div class="order-flex-grow order-margin">
                                <template v-if="!$root.editing">
                                    <template v-if="lineItem.adminNote">
                                        {{lineItem.adminNote}}
                                    </template>
                                    <template v-else>
                                        <span class="light">{{ 'No admin note.' }}</span>
                                    </template>
                                </template>
                                <template v-else>
                                    <label for="note">Admin Note</label>
                                    <textarea v-model="lineItem.adminNote" class="text fullwidth" @input="onChange"></textarea>
                                </template>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="order-indented-block">
                    <div class="order-flex">
                        <div class="order-block-title">
                            <h3>Adjustments</h3>
                        </div>

                        <div class="order-flex-grow">
                            <adjustments
                                    :error-prefix="'order.lineItems.'+lineItemKey+'.adjustments.'"
                                    :adjustments="lineItem.adjustments"
                                    @change="onChange"
                            ></adjustments>
                        </div>
                    </div>
                </div>

                <div class="order-indented-block text-right">
                    <div>
                        <strong>{{ lineItem.totalAsCurrency }}</strong>
                    </div>
                    <div v-if="$root.editing">
                        <a href="#" @click.prevent="$emit('remove')">Remove</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
    import {debounce} from 'debounce'
    import PrismEditor from 'vue-prism-editor'
    import Adjustments from './Adjustments'
    import InputError from './InputError'
    import LineItemStatus from './LineItemStatus'

    export default {
        components: {
            PrismEditor,
            Adjustments,
            InputError,
            LineItemStatus,
        },

        props: {
            lineItem: {
                type: Object,
            },
            lineItemKey: {
                type: Number,
            },
        },

        data() {
            return {
                options: null,
                showSnapshot: false,
            }
        },

        computed: {
            shippingCategory() {
                if (!this.lineItem.shippingCategoryId) {
                    return null
                }

                if (typeof this.$root.shippingCategories[this.lineItem.shippingCategoryId] === 'undefined') {
                    return this.lineItem.shippingCategoryId
                }

                return this.$root.shippingCategories[this.lineItem.shippingCategoryId]
            },

            taxCategory() {
                if (!this.lineItem.taxCategoryId) {
                    return null
                }

                if (typeof this.$root.taxCategories[this.lineItem.taxCategoryId] === 'undefined') {
                    return this.lineItem.taxCategoryId
                }

                return this.$root.taxCategories[this.lineItem.taxCategoryId]
            },
        },

        methods: {
            onOptionsChange() {
                this.lineItem.options = JSON.parse(this.options);
                this.$emit('change')
            },

            onChange() {
                this.$emit('change')
            },
        },

        watch: {
            lineItem() {
                this.options = JSON.stringify(this.lineItem.options, null, '\t')
            }
        },

        mounted() {
            this.options = JSON.stringify(this.lineItem.options, null, '\t')

            this.onChange = debounce(this.onChange, 1000)
            this.onOptionsChange = debounce(this.onOptionsChange, 1000)
        },
    }
</script>

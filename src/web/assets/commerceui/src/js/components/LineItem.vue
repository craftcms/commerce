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
                            <template v-if="!$root.editing">
                                <h3>{{ lineItem.description }}</h3>
                            </template>
                            <template v-else>
                                <label for="selectedPurchasableId" class="hidden">Purchasable</label>
                                <div class="select">
                                    <select v-model="lineItem.purchasableId" @change="onChange">
                                        <option v-for="(option, key) in purchasables" :key="'purchasable-'+key" :value="option.id">
                                            {{ option.sku }} - {{ option.priceAsCurrency}} - {{ option.description }}
                                        </option>
                                    </select>
                                </div>
                                <input-error :error-key="'order.lineItems.'+lineItemKey+'.purchasableId'"></input-error>
                            </template>

                            <!-- Status -->
                            <div>
                                <a class="btn menubtn" ref="lineItemStatus">
                                    <template v-if="lineItemStatus.color">
                                        <span class="status" :class="{[lineItemStatus.color]: true}"></span>
                                    </template>
                                    <template v-else>
                                        <span class="status"></span>
                                    </template>

                                    {{lineItemStatus.name}}
                                </a>
                                <div class="menu">
                                    <ul class="padded" role="listbox">
                                        <li>
                                            <a data-id="0" data-name="None">
                                                <span class="status"></span>
                                                None
                                            </a>
                                        </li>
                                        <li v-for="(status) in lineItemStatuses">
                                            <a
                                                    :data-id="status.id"
                                                    :data-color="status.color"
                                                    :data-name="status.name"
                                                    :class="{sel: lineItemStatus.id === status.value}">
                                                <span class="status" :class="{[status.color]: true}"></span>
                                                {{status.name}}
                                            </a>
                                        </li>
                                    </ul>
                                </div>
                            </div>

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
                                    <template v-if="$root.editing && recalculationMode === 'none'">
                                        <input type="text" class="text" size="10" v-model="lineItem.salePrice" @input="onChange">
                                        <input-error :error-key="'order.lineItems.'+lineItemKey+'.salePrice'"></input-error>
                                    </template>
                                    <template v-else>
                                        {{ lineItem.salePriceAsCurrency }}
                                    </template>
                                </li>
                                <template v-if="lineItem.onSale">
                                    <li><span class="light">Original Price</span> <strike>{{ lineItem.price }}</strike></li>
                                    <li><span class="light">Sale Amount Off</span> {{ lineItem.saleAmount }}</li>
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
                                    :recalculationMode="recalculationMode"
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

    export default {
        components: {
            PrismEditor,
            Adjustments,
            InputError,
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
        },

        data() {
            return {
                options: null,
                showSnapshot: false,
            }
        },

        computed: {
            lineItemStatus() {
                if (this.lineItem.lineItemStatusId !== '0') {
                    for (let lineItemStatusesKey in this.lineItemStatuses) {
                        const lineItemStatus = this.lineItemStatuses[lineItemStatusesKey]

                        if (lineItemStatus.id === this.lineItem.lineItemStatusId) {
                            return lineItemStatus
                        }
                    }
                }

                return {id: "0", name: "None", color: null}
            },
            shippingCategory() {
                if (!this.lineItem.shippingCategoryId) {
                    return null
                }

                if (typeof window.orderEdit.shippingCategories[this.lineItem.shippingCategoryId] === 'undefined') {
                    return this.lineItem.shippingCategoryId
                }

                return window.orderEdit.shippingCategories[this.lineItem.shippingCategoryId]
            },

            taxCategory() {
                if (!this.lineItem.taxCategoryId) {
                    return null
                }

                if (typeof window.orderEdit.taxCategories[this.lineItem.taxCategoryId] === 'undefined') {
                    return this.lineItem.taxCategoryId
                }

                return window.orderEdit.taxCategories[this.lineItem.taxCategoryId]
            },

            purchasables() {
                return this.$root.purchasables
            },

            lineItemStatuses() {
                return window.orderEdit.lineItemStatuses
            }
        },

        methods: {
            onOptionsChange() {
                this.lineItem.options = JSON.parse(this.options);
                this.$emit('change')
            },

            onSelectStatus(status) {
                if (status.dataset.id === '0') {
                    this.lineItem.lineItemStatusId = null
                } else {
                    this.lineItem.lineItemStatusId = status.dataset.id
                }

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

            new Garnish.MenuBtn(this.$refs.lineItemStatus, {
                onOptionSelect: this.onSelectStatus
            })
        },
    }
</script>

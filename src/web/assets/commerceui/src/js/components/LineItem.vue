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
                            <line-item-status :line-item="lineItem"></line-item-status>
                        </div>
                        <div class="order-flex-grow">
                            <ul>
                                <li>
                                    <template v-if="editing && draft.order.recalculationMode === 'none'">
                                        <field label="Sale Price" :errors="getErrors('order.lineItems.'+lineItemKey+'.salePrice')">
                                            <input type="text" class="text" size="10" v-model="lineItem.salePrice" @input="onChange">
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
                                        <input type="text" class="text" size="3" v-model="lineItem.qty" @input="onChange" />
                                    </field>
                                </template>
                            </div>
                        </div>
                        <div class="order-flex-grow text-right">
                            {{lineItem.subtotalAsCurrency}}
                        </div>
                    </div>
                </div>

                <template v-if="Object.keys(lineItem.options).length || editing">
                <div class="order-indented-block">
                    <div class="order-flex">
                        <div class="order-block-title">
                            <h3>Options</h3>
                        </div>

                        <div class="order-flex-grow">
                            <template v-if="!editing">
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
                </template>

                <template v-if="lineItem.note || lineItem.adminNote || editing">
                <div class="order-indented-block">
                    <div class="order-flex">
                        <div class="order-block-title">
                            <h3>Note</h3>
                        </div>

                        <div class="order-flex order-flex-grow order-margin-wrapper">
                            <div class="order-flex-grow order-margin">
                                <template v-if="!editing">
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
                                <template v-if="!editing">
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
                </template>

                <template v-if="lineItem.adjustments.length || editing">
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
                </template>

                <div class="order-indented-block text-right">
                    <div>
                        <strong>{{ lineItem.totalAsCurrency }}</strong>
                    </div>
                    <div v-if="editing">
                        <a href="#" @click.prevent="$emit('remove')">Remove</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
    import {mapState, mapGetters} from 'vuex'
    import {debounce} from 'debounce'
    import PrismEditor from 'vue-prism-editor'
    import Adjustments from './Adjustments'
    import InputError from './InputError'
    import LineItemStatus from './LineItemStatus'
    import Field from './Field'

    export default {
        components: {
            PrismEditor,
            Adjustments,
            InputError,
            LineItemStatus,
            Field,
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
            ...mapState({
                draft: state => state.draft,
                editing: state => state.editing,
            }),

            ...mapGetters([
                'getErrors',
                'shippingCategories',
                'taxCategories',
            ]),

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
        }
    }
</script>

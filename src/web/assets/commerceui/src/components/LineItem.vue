<template>
    <div class="line-item">
        <div class="order-flex">
            <div class="light">
                <div><code>{{ lineItem.sku }}</code></div>
            </div>

            <div class="order-flex-grow">
                <div class="order-indented-block">
                    <div class="order-flex">
                        <div class="order-block-title">
                            <template v-if="!editing">
                                <h3>{{ lineItem.description }}</h3>
                            </template>
                            <template v-else>
                                <label for="selectedPurchasableId">Purchasable</label>
                                <div>
                                    <select v-model="lineItem.purchasableId" @change="onPurchasableChange">
                                        <option v-for="option in purchasables" v-bind:value="option.value">
                                            {{ option.text }}
                                        </option>
                                    </select>
                                </div>
                            </template>

                            <br />
                            <div>
                                <ul>
                                    <li>{{shippingCategory}} <span class="light">(Shipping)</span></li>
                                    <li>{{taxCategory}} <span class="light">(Tax)</span></li>
                                </ul>
                            </div>
                        </div>
                        <div class="order-flex-grow">
                            <ul>
                                <template v-if="lineItem.onSale">
                                    <li><span class="light">Original Price</span> <strike>{{ lineItem.price }}</strike></li>
                                    <li><span class="light">Sale Amount Off</span> {{ lineItem.saleAmount }}</li>
                                </template>
                                <li>
                                    <label class="light" for="salePrice">Sale Price</label>
                                    <template v-if="editing && recalculateMode === 'manual'">
                                        <input type="text" class="text" size="10" :value="lineItem.salePrice">
                                    </template>
                                    <template v-else>
                                        {{ lineItem.salePriceAsCurrency }}
                                    </template>
                                </li>
                            </ul>

                        </div>
                        <div class="order-flex-grow">
                            <div>
                                <label class="light" for="quantity">Quantity</label>
                                <template v-if="!editing">
                                    {{ lineItem.qty }}
                                </template>
                                <template v-else>
                                    <input type="text" class="text" size="3" v-model="lineItem.qty" @input="onQuantityChange" />
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
                            <template v-if="!editing">
                                <template v-if="Object.keys(lineItem.options).length">
                                    <ul :id="'info-' + lineItem.id">
                                        <template v-for="(option, key) in lineItem.options">
                                            <li>
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
                                    <textarea v-model="lineItem.note" class="text fullwidth" @input="onNoteChange"></textarea>
                                </template>
                            </div>
                            <div class="order-flex-grow order-margin">
                                <template v-if="!editing">
                                    <template v-if="lineItem.note">
                                        {{lineItem.note}}
                                    </template>
                                    <template v-else>
                                        <span class="light">{{ 'No admin note.' }}</span>
                                    </template>
                                </template>
                                <template v-else>
                                    <label for="note">Admin Note</label>
                                    <textarea :value="lineItem.note" class="text fullwidth"></textarea>
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
                            <template v-for="adjustment in lineItem.adjustments">
                                <div class="order-flex">
                                    <div class="order-flex-grow">
                                        <div>
                                            {{adjustment.name}}
                                            <span class="light">({{adjustment.type}})</span>
                                            {{adjustment.description}}

                                            <template v-if="editing && recalculateMode === 'manual'">
                                                <a href="#">Remove</a>
                                            </template>
                                        </div>
                                    </div>
                                    <div class="order-flex-grow text-right">
                                        <template v-if="adjustment.included !== '0' && adjustment.included !== false">
                                            <div class="light">{{adjustment.amountAsCurrency}} included</div>
                                        </template>
                                        <template v-else>
                                            {{adjustment.amountAsCurrency}}
                                        </template>
                                    </div>
                                </div>
                            </template>

                            <template v-if="editing && recalculateMode === 'manual'">
                                <div>
                                    <a href="#">Add an adjustment</a>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>

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
    import {debounce} from 'debounce'
    import PrismEditor from 'vue-prism-editor'

    export default {
        components: {
            PrismEditor
        },

        props: {
            lineItem: {
                type: Object,
            },
            lineItemKey: {
                type: Number,
            },
            draft: {
                type: Object,
            },
            editing: {
                type: Boolean,
            },
            recalculateMode: {
                type: String,
            },
        },

        data() {
            return {
                options: null,
                code: 'const a = b',
            }
        },

        computed: {
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
                return window.orderEdit.purchasableIds
            }
        },

        methods: {
            onNoteChange() {
                this.$emit('noteChange')
            },

            onOptionsChange() {
                this.lineItem.options = JSON.parse(this.options);
                this.$emit('noteChange')
            },

            onPurchasableChange() {
                this.$emit('purchasableChange')
            },

            onQuantityChange() {
                this.$emit('quantityChange')
            }
        },

        watch: {
            lineItem() {
                this.options = JSON.stringify(this.lineItem.options, null, '\t')
            }
        },

        mounted() {
            this.options = JSON.stringify(this.lineItem.options, null, '\t')

            this.onNoteChange = debounce(this.onNoteChange, 1000)
            this.onOptionsChange = debounce(this.onOptionsChange, 1000)
            this.onQuantityChange = debounce(this.onQuantityChange, 1000)
        },
    }
</script>

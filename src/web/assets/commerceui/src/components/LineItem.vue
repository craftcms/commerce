<template>
    <div class="line-item">
        <div class="order-flex">
            <div class="light">
                <strong>SKU</strong>
                <div><code>{{ lineItem.sku }}</code></div>
            </div>

            <div class="order-flex-grow">
                <div class="order-indented-block">
                    <div class="order-flex">
                        <div class="order-block-title">
                            <h3>{{ lineItem.description }}</h3>
                        </div>
                        <div class="order-flex-grow">
                            <ul>
                                <template v-if="lineItem.onSale">
                                    <li><span class="light">Original Price</span> <strike>{{ lineItem.price }}</strike></li>
                                    <li><span class="light">Sale Amount Off</span> {{ lineItem.saleAmount }}</li>
                                </template>
                                <li>
                                    <label class="light" for="salePrice">Sale Price</label>
                                    <template v-if="!editing">
                                        {{ lineItem.salePriceAsCurrency }}
                                    </template>
                                    <template v-else>
                                        <input type="text" class="text" size="10" :value="lineItem.salePrice">
                                    </template>
                                </li>
                            </ul>

                        </div>
                        <div class="order-flex-grow">
                            <div>
                                <label class="light" for="quantity">Quantity</label>
                                <input type="text" class="text" size="3" v-model="lineItem.qty" @input="$emit('quantityChange')" />
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

                        <div class="order-grow">
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
                                    <span class="light">{{ lineItem.note ? lineItem.note : 'No customer note.' }}</span>
                                </template>
                                <template v-else>
                                    <label for="note">Customer Note</label>
                                    <textarea :value="lineItem.note" class="text fullwidth"></textarea>
                                </template>
                            </div>
                            <div class="order-flex-grow order-margin">
                                <template v-if="!editing">
                                    <span class="light">{{ lineItem.note ? lineItem.note : 'No admin note.' }}</span>
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

                                            <template v-if="editing">
                                                <a href="#">Remove</a>
                                            </template>
                                        </div>
                                    </div>
                                    <div class="order-flex-grow text-right">
                                        <template v-if="adjustment.included !== '0'">
                                            <div class="light">{{adjustment.amountAsCurrency}} included</div>
                                        </template>
                                        <template v-else>
                                            {{adjustment.amountAsCurrency}}
                                        </template>
                                    </div>
                                </div>
                            </template>

                            <template v-if="editing">
                                <div>
                                    <a href="#">Add an adjustment</a>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>

                <div class="order-indented-block">
                    <div class="order-flex">
                        <div class="order-block-title">
                            <h3>Shipping &amp; Tax</h3>
                        </div>
                        <div>
                            <div>
                                <strong>Shipping Category:</strong> {{shippingCategory}}<br>
                                <strong>Tax Category:</strong> {{taxCategory}}<br>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="order-indented-block text-right">
                    <div>
                        <strong>{{ lineItem.subtotalAsCurrency }}</strong>
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
    export default {
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
            }
        }
    }
</script>

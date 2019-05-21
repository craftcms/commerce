<template>
    <div class="line-item">
        <div class="line-item-flex">
            <div class="light">
                <strong>SKU</strong>
                <div><code>{{ lineItem.sku }}</code></div>
            </div>

            <div class="line-item-flex-grow">
                <div class="line-item-block">
                    <div class="line-item-flex">
                        <div class="line-item-flex-grow">
                            <h3>{{ lineItem.description }}</h3>
                        </div>
                        <div class="line-item-flex-grow">
                            <template v-if="lineItem.onSale">
                                <div>
                                    <strong>Original Price: </strong><strike>{{ lineItem.price }}</strike><br>
                                    <strong>Sale Amount Off: </strong>{{ lineItem.saleAmount }}<br>
                                </div>
                            </template>

                            <div>
                                {{ lineItem.salePriceAsCurrency }}
                            </div>
                        </div>
                        <div class="line-item-flex-grow">
                            <div>
                                <input type="text" class="text" size="3" v-model="lineItem.qty" @input="$emit('quantityChange')" />
                            </div>
                        </div>
                        <div class="line-item-flex-grow">
                            {{lineItem.subtotalAsCurrency}}
                        </div>
                    </div>
                </div>
                <div class="line-item-block">
                    <h3>Options</h3>

                    <template v-if="Object.keys(lineItem.options).length">
                        <div :id="'info-' + lineItem.id">
                            <template v-for="(option, key) in lineItem.options">
                                {{key}}:

                                <template v-if="Array.isArray(option)">
                                    <code>{{ option }}</code>
                                </template>

                                <template v-else>{{ option }}</template>
                                <br>
                            </template>
                        </div>
                    </template>
                </div>
                <div class="line-item-block">
                    <h3>Note</h3>
                    <template v-if="lineItem.note">
                        <span class="info">{{ lineItem.note }}</span>
                    </template>
                    <textarea :value="lineItem.note" class="text"></textarea>
                </div>

                <div class="line-item-block">
                    <div class="line-item-flex">
                        <div class="line-item-flex">
                            <h3>Adjustments</h3>
                        </div>

                        <div class="line-item-flex-grow">
                            <template v-for="adjustment in lineItem.adjustments">
                                <div class="line-item-flex">
                                    <div class="line-item-flex-grow">
                                        <div>
                                            {{adjustment.name}}
                                            ({{adjustment.type}})

                                            {{adjustment.description}}
                                        </div>
                                    </div>
                                    <div class="line-item-flex-grow text-right">
                                        <template v-if="adjustment.included">
                                            <div class="light">{{adjustment.amountAsCurrency}} included</div>
                                        </template>
                                        <template v-else>
                                            {{adjustment.amountAsCurrency}}
                                        </template>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>

                <div class="line-item-block">
                    <h3>Shipping &amp; Tax</h3>
                    <div>
                        <strong>Shipping Category:</strong> {{ lineItem.shippingCategoryId }}<br>
                        <strong>Tax Category:</strong> {{ lineItem.taxCategoryId }}<br>
                    </div>
                </div>

                <div class="line-item-block text-right">
                    <div>
                        <strong>{{ lineItem.subtotalAsCurrency }}</strong>
                    </div>
                    <div><a href="#" @click.prevent="$emit('remove')">Remove</a></div>
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
        }
    }
</script>

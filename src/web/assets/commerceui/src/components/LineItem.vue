<template>
    <tr class="infoRow">
        <td>
            <div>
                <strong>Description:</strong>{{ lineItem.description }}<br>
                <strong>SKU:</strong> {{ lineItem.sku }}<br>
                <strong title="Ben, get category name from window.orderEdit.shippingCategories)">Shipping Category:</strong> {{ lineItem.shippingCategoryId }}<br>
                <strong title="Ben, get category name from window.orderEdit.taxCategories)">Tax Category:</strong> {{ lineItem.taxCategoryId }}<br>
                <template v-if="lineItem.onSale">
                    <strong>Original Price: </strong><strike>{{ lineItem.price }}</strike><br>
                    <strong>Sale Amount Off: </strong>{{ lineItem.saleAmount }}<br>
                </template>
            </div>

            <template v-if="lineItem.options.length">
                <a class="fieldtoggle first last" :data-target="'info-' + lineItem.id">{{ "Options" }}</a>
                <span :id="'info-' + lineItem.id" class="hidden">
                    <template v-for="(key, option) in lineItem.options">
                        {{key}}:

                        <template v-if="Array.isArray(option)">
                            <code>{{ option }}</code>
                        </template>

                        <template v-else>{{ option }}</template>
                        <br>
                    </template>
                </span>
            </template>
        </td>
        <td data-title="Note">
            <template v-if="lineItem.note">
                <span class="info">{{ lineItem.note }}</span>
            </template>
            <textarea :value="lineItem.note" class="text"></textarea>
        </td>
        <td data-title="Price">
            {{ lineItem.salePriceAsCurrency }}
        </td>
        <td data-title="Qty">
            <input type="text" class="text" size="3" v-model="lineItem.qty" @input="$emit('quantityChange')" />
        </td>
        <td></td>
        <td data-title="Sub-total">
            <span class="right">{{ lineItem.subtotalAsCurrency }}</span>
        </td>
        <td>
        </td>
        <td>
            <a href="#" @click.prevent="$emit('remove')">Remove</a>
        </td>
    </tr>
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

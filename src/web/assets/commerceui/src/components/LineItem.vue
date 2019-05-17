<template>
    <tr class="infoRow">
        <td>
            <span class="description">{{ lineItem.description }} ({{ lineItem.purchasableId }})</span>

            <br><span class="code">{{ lineItem.sku }}</span>

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
            {{ lineItem.salePrice }}
        </td>
        <td data-title="Qty">
            <input type="text" class="text" size="3" v-model="lineItem.qty" @input="$emit('quantityChange')" />
        </td>
        <td></td>
        <td data-title="Sub-total">
            <span class="right">{{ lineItem.subtotal }}</span>
        </td>
        <td>
            SubTotal:{{ lineItem.subTotal }}<br>
            Total (with adjustments):{{ lineItem.total }}<br>
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

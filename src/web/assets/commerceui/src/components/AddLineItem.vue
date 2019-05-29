<template>
    <form @submit.prevent="lineItemAdd()">
        <v-select label="sku" v-model="selectedPurchasable" :options="$root.purchasables"></v-select>

        <br />

        <input type="submit" class="btn submit" value="Add Line Item" />

        <div v-if="loading" class="spinner"></div>
    </form>
</template>

<script>
    import axios from 'axios'
    import VSelect from 'vue-select'

    export default {
        components: {
            VSelect,
        },
        props: {
            draft: {
                type: Object,
            },
            loading: {
                type: Boolean,
            },
            orderId: {
                type: Number,
            }
        },

        data() {
            return {
                selectedPurchasable: null,
                vselectSelected: null,
            }
        },

        methods: {
            lineItemAdd() {
                const lineItem = {
                    id: null,
                    lineItemStatusId: null,
                    salePrice: '0.0000',
                    qty: "1",
                    note: "",
                    orderId: this.orderId,
                    purchasableId: this.selectedPurchasable.id,
                    sku: this.selectedPurchasable.sku,
                    options: {giftWrapped: "no"},
                    adjustments: [],
                }

                this.draft.order.lineItems.push(lineItem)

                this.$emit('change')
            },
        },
    }
</script>
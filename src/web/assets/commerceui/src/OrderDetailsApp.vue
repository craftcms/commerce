<template>
    <div>
        <div class="order-details pane">
            <template v-if="!draft">
                <div class="spinner"></div>
            </template>
            <template v-else>
                <table id="" class="data fullwidth collapsible">
                    <thead>
                    <tr>
                        <th scope="col">Item</th>
                        <th scope="col">Note</th>
                        <th scope="col">Price</th>
                        <th scope="col">Quantity</th>
                        <th scope="col"></th>
                        <th scope="col"></th>
                        <th scope="col"></th>
                        <th scope="col"></th>
                    </tr>
                    </thead>
                    <tbody>
                    <template v-for="(lineItem, lineItemKey) in draft.order.lineItems">
                        <line-item
                                :draft="draft"
                                :line-item="lineItem"
                                :line-item-key="lineItemKey"
                                @quantityChange="saveOrder(draft)"
                                @remove="lineItemRemove(lineItemKey)"></line-item>

                        <template v-for="adjustment in lineItem.adjustments">
                            <line-item-adjustment :adjustment="adjustment"></line-item-adjustment>
                        </template>
                    </template>

                    <tr>
                        <td></td>
                        <td></td>
                        <td></td>
                        <td></td>
                        <td><strong>{{ "Items Total (with adjustments)" }}</strong></td>
                        <td>
                            <span class="right">{{ draft.order.itemTotal|currency }}</span>
                        </td>
                        <td></td>
                        <td></td>
                    </tr>

                    <template v-for="adjustment in draft.order.orderAdjustments">
                        <order-adjustment :adjustment="adjustment"></order-adjustment>
                    </template>

                    <tr>
                        <td></td>
                        <td>
                            <template v-if="draft.order.isPaid && draft.order.totalPrice > 0">
                                <div class="paidLogo"><span>{{ 'PAID' }}</span></div>
                            </template>
                        </td>
                        <td></td>
                        <td></td>
                        <td><h2>{{ "Total Price" }}</h2></td>
                        <td>
                            <h2 class="right">{{ draft.order.totalPrice|currency }}</h2>
                        </td>
                        <td></td>
                        <td></td>
                    </tr>

                    </tbody>
                </table>

                <hr>

                <form @submit.prevent="lineItemAdd()">
                    <div>
                        <label for="selectedPurchasableId">Purchasable</label>
                        <div>
                            <select v-model="selectedPurchasableId">
                                <option v-for="option in purchasables" v-bind:value="option.value">
                                    {{ option.text }}
                                </option>
                            </select>
                        </div>
                    </div>

                    <br />

                    <input type="submit" class="btn submit" value="Add Line Item" />

                    <div v-if="loading" class="spinner"></div>
                </form>
            </template>
        </div>
    </div>
</template>

<script>
    import axios from 'axios'
    import OrderAdjustment from './components/OrderAdjustment'
    import LineItem from './components/LineItem'
    import LineItemAdjustment from './components/LineItemAdjustment'

    export default {
        name: 'order-details-app',

        components: {
            OrderAdjustment,
            LineItem,
            LineItemAdjustment,
        },

        data() {
            return {
                loading: false,
                draft: null,
                selectedPurchasableId: 4
            }
        },

        computed: {
            orderId() {
                return window.orderEdit.orderId
            },
            purchasables() {
                return window.orderEdit.purchasableIds
            }
        },

        methods: {
            lineItemAdd() {
                const lineItem = {
                    qty: "1",
                    note: "",
                    orderId: this.orderId,
                    purchasableId: this.selectedPurchasableId,
                    options: {giftWrapped: "no"},
                }

                const draft = JSON.parse(JSON.stringify(this.draft))

                draft.order.lineItems.push(lineItem)

                this.saveOrder(draft)
            },

            lineItemRemove(lineItemKey) {
                this.$delete(this.draft.order.lineItems, lineItemKey)
                this.saveOrder(this.draft)
            },

            getOrder(orderId) {
                this.loading = true
                axios.get(Craft.getActionUrl('commerce/order/get', {orderId}))
                    .then((response) => {
                        this.loading = false
                        this.draft = JSON.parse(JSON.stringify(response.data))
                    })
                    .catch(() => {
                        this.loading = false
                        console.log('error')
                    })
            },

            saveOrder(draft) {
                this.loading = true

                axios.post(Craft.getActionUrl('commerce/order/save'), draft)
                    .then((response) => {
                        this.loading = false
                        this.draft = JSON.parse(JSON.stringify(response.data))
                    })
                    .catch(() => {
                        this.loading = false
                        console.log('error')
                    })
            }
        },

        mounted() {
            this.getOrder(this.orderId)
        }

    }
</script>

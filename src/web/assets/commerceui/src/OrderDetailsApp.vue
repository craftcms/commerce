<template>
    <div>
        <div class="order-details pane">
            <template v-if="!draft">
                <div class="spinner"></div>
            </template>
            <template v-else>
                <!-- Is Paid -->
                <template v-if="draft.order.isPaid && draft.order.totalPrice > 0">
                    <div class="paidLogo"><span>{{ 'PAID' }}</span></div>
                </template>

                <!-- Line Items -->
                <template v-for="(lineItem, lineItemKey) in draft.order.lineItems">
                    <line-item
                            :draft="draft"
                            :line-item="lineItem"
                            :line-item-key="lineItemKey"
                            @quantityChange="saveOrder(draft)"
                            @remove="lineItemRemove(lineItemKey)"></line-item>
                </template>

                <hr />

                <!-- Order Adjustments -->
                <div class="line-item-flex">
                    <div class="line-item-flex">
                        <h3>Adjustments</h3>
                    </div>

                    <div class="line-item-flex-grow">
                        <template v-for="adjustment in draft.order.orderAdjustments">
                            <order-adjustment :adjustment="adjustment"></order-adjustment>
                        </template>
                    </div>
                </div>

                <hr />

                <!-- Total Price -->
                <div class="text-right">
                    <h2>{{ "Total Price" }}</h2>
                    <h2>{{ draft.order.totalPriceAsCurrency }}</h2>
                </div>

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

<style lang="scss">
    @import './sass/order-details.scss';
</style>

<script>
    import axios from 'axios'
    import OrderAdjustment from './components/OrderAdjustment'
    import LineItem from './components/LineItem'

    export default {
        name: 'order-details-app',

        components: {
            OrderAdjustment,
            LineItem,
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

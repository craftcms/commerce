<template>
    <div>
        <div>
            <template v-if="!editing">
                <a class="btn" @click.prevent="editing = true">Edit</a>
                <div v-if="loading" class="spinner"></div>
            </template>

            <template v-else>
                <div class="order-flex">
                    <div>
                        <a class="btn" @click.prevent="cancel()">Cancel</a>
                        <div v-if="loading" class="spinner"></div>
                    </div>

                    <div class="order-flex-grow text-right">
                        <div>
                            Recalculate whole order
                            <input type="radio" value="auto" v-model="recalculateMode" />
                        </div>

                        <div>
                            Manually edit
                            <input type="radio" value="manual" v-model="recalculateMode" />
                        </div>
                    </div>
                </div>
            </template>
        </div>

        <hr>

        <div class="order-details">
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
                            :editing="editing"
                            @optionsChange="saveOrder(draft)"
                            @noteChange="saveOrder(draft)"
                            @quantityChange="saveOrder(draft)"
                            @remove="lineItemRemove(lineItemKey)"></line-item>
                </template>

                <!-- Order Adjustments -->
                <div class="order-flex">
                    <div class="order-block-title">
                        <h3>Adjustments</h3>
                    </div>

                    <div class="order-flex-grow">
                        <template v-for="adjustment, adjustmentKey in draft.order.orderAdjustments">
                            <order-adjustment
                                    :editing="editing"
                                    :adjustment="adjustment"
                                    :adjustmentKey="adjustmentKey"
                                    @remove="removeAdjustment(adjustmentKey)"></order-adjustment>
                        </template>

                        <template v-if="editing">
                            <div>
                                <a href="#">Add an adjustment</a>
                            </div>
                        </template>
                    </div>
                </div>

                <hr />

                <!-- Total Price -->
                <div class="text-right">
                    <h2>{{ "Total Price" }}</h2>
                    <h2>{{ draft.order.totalPriceAsCurrency }}</h2>
                </div>

                <template v-if="editing">
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
                editing: false,
                loading: false,
                originalDraft: null,
                draft: null,
                selectedPurchasableId: 4,
                recalculateMode: 'auto',
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
                return axios.get(Craft.getActionUrl('commerce/order/get', {orderId}))
                    .then((response) => {
                        this.loading = false
                        this.draft = JSON.parse(JSON.stringify(response.data))

                        if (!this.originalDraft) {
                            this.originalDraft = JSON.parse(JSON.stringify(this.draft))
                        }
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
            },

            cancel() {
                this.editing = false
                this.draft = JSON.parse(JSON.stringify(this.originalDraft))
            },

            removeAdjustment(key) {
                this.$delete(this.draft.order.orderAdjustments, key)
            }
        },

        mounted() {
            this.getOrder(this.orderId)
        }

    }
</script>

<template>
    <div>
        <div>
            <template v-if="!editing">
                <a class="btn" @click.prevent="editing = true">Edit</a>
                <div v-if="loading" class="spinner"></div>
            </template>

            <template v-else>
                <div class="order-flex">
                    <div class="order-row-title">
                        <a class="btn" @click.prevent="cancel()">Cancel</a>
                        <div v-if="loading" class="spinner"></div>
                    </div>

                    <div class="order-recalculate-modes order-flex-grow order-flex">
                        <div class="order-recalculate-mode order-flex">
                            <div class="input">
                                <input id="recalculate-all" type="radio" value="all" v-model="draft.order.recalculationMode" @click="confirmAutoCalculation" @change="recalculationModeChange" />
                            </div>
                            <div>
                                <label for="recalculate-all">
                                    <strong>Recalculate whole order</strong>

                                    <div class="instructions">
                                        In this mode, the order will auto-calculate sales and adjustments like tax and shipping based on the items in the order and the configuration of the system.
                                    </div>
                                </label>
                            </div>
                        </div>

                        <div class="order-recalculate-mode order-flex">
                            <div class="input">
                                <input id="recalculate-none" type="radio" value="none" v-model="draft.order.recalculationMode" @change="recalculationModeChange" />
                            </div>
                            <div>
                                <label for="recalculate-none">
                                    <strong>Manually edit</strong>

                                    <div class="instructions">
                                        In this mode, the order can be edited manually including all line item prices and adjustments. No adjustments like discounts and shipping will be calculated for you.
                                    </div>
                                </label>
                            </div>
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
                            :key="lineItemKey"
                            :draft="draft"
                            :line-item="lineItem"
                            :line-item-key="lineItemKey"
                            :editing="editing"
                            :recalculate-mode="recalculateMode"
                            @purchasableChange="saveOrder(draft)"
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
                        <template v-for="(adjustment, adjustmentKey) in draft.order.orderAdjustments">
                            <order-adjustment
                                    :key="adjustmentKey"
                                    :editing="editing"
                                    :adjustment="adjustment"
                                    :adjustmentKey="adjustmentKey"
                                    :recalculate-mode="recalculateMode"
                                    @remove="removeAdjustment(adjustmentKey)"></order-adjustment>
                        </template>

                        <template v-if="editing && recalculateMode === 'none'">
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
                            <div class="input">
                                <div class="select">
                                    <select v-model="selectedPurchasableId">
                                        <option v-for="(option, key) in purchasables" :key="key" :value="option.value">
                                            {{ option.text }}
                                        </option>
                                    </select>
                                </div>
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
    /* globals Craft */

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
                recalculateMode: 'all',
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
            confirmAutoCalculation(ev) {
                const ret = confirm("Are you sure you want to switch to recalculate whole order? You will loose all of your manual adjustments.");

                if (!ret) {
                    ev.preventDefault()
                }
            },

            recalculationModeChange() {
                this.saveOrder(this.draft)
            },

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
                    .catch((error) => {
                        this.loading = false

                        let errorMsg = 'Couldn’t get order.'

                        if (error.response.data.error) {
                            errorMsg = error.response.data.error
                        }

                        Craft.cp.displayError(errorMsg);

                        throw errorMsg + ': '+ error.response
                    })
            },

            saveOrder(draft) {
                this.loading = true

                axios.post(Craft.getActionUrl('commerce/order/save'), draft)
                    .then((response) => {
                        this.loading = false
                        this.draft = JSON.parse(JSON.stringify(response.data))

                        Craft.cp.displayNotice('Order recalculated.');
                    })
                    .catch((error) => {
                        this.loading = false

                        let errorMsg = 'Couldn’t recalculate order.'
                        
                        if (error.response.data.error) {
                            errorMsg = error.response.data.error
                        }

                        Craft.cp.displayError(errorMsg);

                        throw errorMsg + ': '+ error.response
                    })
            },

            cancel() {
                this.editing = false
                this.draft = JSON.parse(JSON.stringify(this.originalDraft))
            },

            removeAdjustment(key) {
                this.$delete(this.draft.order.orderAdjustments, key)
                this.saveOrder(this.draft)
            }
        },

        mounted() {
            this.getOrder(this.orderId)
        }

    }
</script>

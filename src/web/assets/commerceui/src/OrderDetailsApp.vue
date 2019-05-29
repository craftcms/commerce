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
                        <div class="buttons">
                            <a class="btn" @click.prevent="cancel()">Cancel</a>
                            <a class="btn submit" @click.prevent="save()">Save</a>
                            <div v-if="loading" class="spinner"></div>
                        </div>
                    </div>

                    <div class="order-flex-grow text-right">
                        <a class="btn" @click.prevent="recalculate()">Recalculate</a>
                    </div>
                </div>
            </template>
        </div>

        <template v-if="editing">
            <hr>

            <add-line-item
                    :order-id="orderId"
                    :draft="draft"
                    :loading="loading"
                    @change="recalculateOrder(draft)"
            ></add-line-item>
        </template>

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
                            :recalculation-mode="draft.order.recalculationMode"
                            @change="recalculateOrder(draft)"
                            @remove="lineItemRemove(lineItemKey)"></line-item>
                </template>

                <!-- Order Adjustments -->
                <div class="order-flex">
                    <div class="order-block-title">
                        <h3>Adjustments</h3>
                    </div>

                    <div class="order-flex-grow">
                        <adjustments
                                :adjustments="draft.order.orderAdjustments"
                                :draft="draft"
                                :editing="editing"
                                :recalculationMode="draft.order.recalculationMode"
                                @change="recalculateOrder(draft)"
                        ></adjustments>
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

                    <add-line-item
                            :order-id="orderId"
                            :draft="draft"
                            :loading="loading"
                            @change="recalculateOrder(draft)"
                    ></add-line-item>
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
    import LineItem from './components/LineItem'
    import Adjustments from './components/Adjustments'
    import AddLineItem from './components/AddLineItem'

    export default {
        name: 'order-details-app',

        components: {
            LineItem,
            Adjustments,
            AddLineItem
        },

        data() {
            return {
                editing: false,
                loading: false,
                originalDraft: null,
                draft: null,
            }
        },

        computed: {
            orderId() {
                return window.orderEdit.orderId
            },
        },

        methods: {
            recalculate() {
                const draft = JSON.parse(JSON.stringify(this.draft))
                draft.order.recalculationMode = 'all'
                this.recalculateOrder(draft)
            },

            lineItemRemove(lineItemKey) {
                this.$delete(this.draft.order.lineItems, lineItemKey)
                this.recalculateOrder(this.draft)
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

            recalculateOrder(draft) {
                this.loading = true

                axios.post(Craft.getActionUrl('commerce/order/recalculate'), draft)
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

            save() {
                this.loading = true

                axios.post(Craft.getActionUrl('commerce/order/save'), this.draft)
                    .then((response) => {
                        this.loading = false
                        Craft.cp.displayNotice('Success.');
                    })
                    .catch((error) => {
                        this.loading = false
                        Craft.cp.displayError('Error.');
                    })
            },

            cancel() {
                this.editing = false
                this.draft = JSON.parse(JSON.stringify(this.originalDraft))
            },

            removeAdjustment(key) {
                this.$delete(this.draft.order.orderAdjustments, key)
                this.recalculateOrder(this.draft)
            }
        },

        mounted() {
            this.getOrder(this.orderId)

            axios.get(Craft.getActionUrl('commerce/purchasables/search', {orderId: this.orderId}))
                .then((response) => {
                    this.$root.purchasables = response.data
                })
        }

    }
</script>

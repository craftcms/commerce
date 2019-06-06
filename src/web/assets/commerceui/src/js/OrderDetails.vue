<template>
    <div v-if="draft">
        <div class="order-details" :class="{'order-opacity-50': recalculateLoading || saveLoading}">
            <template v-if="!draft">
                <div class="spinner"></div>
            </template>
            <template v-else>
                <is-paid :is-paid="isPaid"></is-paid>

                <line-items
                        :line-items="lineItems"
                        :editing="editing"
                        :recalculation-mode="recalculationMode"
                        @updateLineItems="updateLineItems"
                        @change="recalculateOrder(draft)"
                ></line-items>

                <order-adjustments
                        :adjustments="orderAdjustments"
                        :editing="editing"
                        :recalculation-mode="recalculationMode"
                        @updateOrderAdjustments="updateOrderAdjustments"
                        @change="recalculateOrder(draft)"
                ></order-adjustments>

                <hr />

                <total :order="draft.order"></total>

                <template v-if="editing">
                    <hr>

                    <add-line-item
                            :order-id="orderId"
                            @change="recalculateOrder(draft)"
                    ></add-line-item>
                </template>
            </template>

            <template v-if="draft.order.errors">
                <pre>{{draft.order.errors}}</pre>
            </template>
        </div>
    </div>
</template>

<style lang="scss">
    @import '../sass/order-details.scss';
</style>

<script>
    import {mapState, mapActions} from 'vuex'
    import LineItems from './components/LineItems'
    import AddLineItem from './components/AddLineItem'
    import OrderAdjustments from './components/OrderAdjustments'
    import IsPaid from './components/IsPaid'
    import Total from './components/Total'

    export default {
        name: 'order-details-app',

        components: {
            AddLineItem,
            LineItems,
            OrderAdjustments,
            IsPaid,
            Total,
        },

        computed: {
            ...mapState({
                // draft: state => state.draft,
                recalculateLoading: state => state.recalculateLoading,
                saveLoading: state => state.saveLoading,
                orderId: state => state.orderId,
                editing: state => state.editing,
            }),

            recalculationMode() {
              return this.draft.order.recalculationMode
            },

            lineItems: {
                get() {
                    return this.draft.order.lineItems
                },

                set(lineItems) {
                    const draft = JSON.parse(JSON.stringify(this.draft))
                    draft.order.lineItems = lineItems
                    this.$store.commit('updateDraft', draft)
                    this.recalculateOrder(this.draft)
                }
            },

            orderAdjustments: {
                get() {
                    return this.draft.order.orderAdjustments
                },

                set(adjustments) {
                    const draft = JSON.parse(JSON.stringify(this.draft))
                    draft.order.orderAdjustments = adjustments
                    this.$store.commit('updateDraft', draft)
                    this.recalculateOrder(this.draft)
                }
            },

            draft: {
                get() {
                    return JSON.parse(JSON.stringify(this.$store.state.draft))
                },

                set(newVal) {
                    const draft = JSON.parse(JSON.stringify(newVal));
                    this.$store.commit('updateDraft', draft)
                }
            },

            isPaid() {
                return this.draft.order.isPaid && this.draft.order.totalPrice > 0
            },
        },

        methods: {
            ...mapActions([
                'recalculateOrder',
            ]),

            updateLineItems(lineItems) {
                const draft = JSON.parse(JSON.stringify(this.$store.state.draft))
                draft.order.lineItems = lineItems
                this.draft = draft
                this.recalculateOrder(this.draft)
            },

            updateOrderAdjustments(orderAdjustments) {
                const draft = JSON.parse(JSON.stringify(this.$store.state.draft))
                draft.order.orderAdjustments = orderAdjustments
                this.draft = draft
                this.recalculateOrder(this.draft)
            },
        },
    }
</script>

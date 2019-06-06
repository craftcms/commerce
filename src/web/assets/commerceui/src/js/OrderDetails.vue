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
                ></line-items>

                <order-adjustments
                        :adjustments="orderAdjustments"
                        :editing="editing"
                        :recalculation-mode="recalculationMode"
                        @updateOrderAdjustments="updateOrderAdjustments"
                ></order-adjustments>

                <hr />

                <total :order="draft.order"></total>

                <template v-if="editing">
                    <hr>

                    <add-line-item @addLineItem="addLineItem"></add-line-item>
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
    import {debounce} from 'debounce'
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
                recalculateLoading: state => state.recalculateLoading,
                saveLoading: state => state.saveLoading,
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
                    this.recalculate()
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
                    this.recalculate()
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

            addLineItem(lineItem) {
                const lineItems = JSON.parse(JSON.stringify(this.lineItems))
                lineItems.push(lineItem)
                this.updateLineItems(lineItems)
            },

            updateLineItems(lineItems) {
                const draft = JSON.parse(JSON.stringify(this.$store.state.draft))
                draft.order.lineItems = lineItems
                this.draft = draft
                this.recalculate()
            },

            updateOrderAdjustments(orderAdjustments) {
                const draft = JSON.parse(JSON.stringify(this.$store.state.draft))
                draft.order.orderAdjustments = orderAdjustments
                this.draft = draft
                this.recalculate()
            },

            recalculate() {
                this.recalculateOrder()
            },
        },

        mounted() {
            this.recalculate = debounce(this.recalculate, 1000)
        }
    }
</script>

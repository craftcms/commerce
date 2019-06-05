<template>
    <div v-if="draft">
        <!-- Header -->
        <div class="text-right">
            <div v-if="recalculateLoading" class="spinner"></div>
            <a class="btn" @click.prevent="autoRecalculate()">Recalculate</a>
        </div>

        <hr>

        <!-- Order details -->
        <div class="order-details" :class="{'order-opacity-50': recalculateLoading || saveLoading}">
            <template v-if="!draft">
                <div class="spinner"></div>
            </template>
            <template v-else>
                <!-- Is Paid -->
                <template v-if="draft.order.isPaid && draft.order.totalPrice > 0">
                    <div class="paidLogo"><span>{{ 'PAID' }}</span></div>
                </template>

                <!-- Line Items -->
                <line-items></line-items>

                <!-- Order Adjustments -->
                <div class="order-flex">
                    <div class="order-block-title">
                        <h3>Adjustments</h3>
                    </div>

                    <div class="order-flex-grow">
                        <adjustments
                                :adjustments="draft.order.orderAdjustments"
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
                            @change="recalculateOrder(draft)"
                    ></add-line-item>
                </template>
            </template>
        </div>

        <!-- Errors -->
        <template v-if="draft.order.errors">
            <pre>{{draft.order.errors}}</pre>
        </template>
    </div>
</template>

<style lang="scss">
    @import '../sass/order-details.scss';
</style>

<script>
    import {mapState, mapActions} from 'vuex'
    import LineItems from './components/LineItems'
    import Adjustments from './components/Adjustments'
    import AddLineItem from './components/AddLineItem'

    export default {
        name: 'order-details-app',

        components: {
            Adjustments,
            AddLineItem,
            LineItems
        },

        computed: {
            ...mapState({
                draft: state => state.draft,
                recalculateLoading: state => state.recalculateLoading,
                saveLoading: state => state.saveLoading,
                orderId: state => state.orderId,
                editing: state => state.editing,
            }),
        },

        methods: {
            ...mapActions([
                'recalculateOrder',
            ]),

            autoRecalculate() {
                const draft = JSON.parse(JSON.stringify(this.draft))
                draft.order.recalculationMode = 'all'
                this.recalculateOrder(draft)
            },

            removeAdjustment(key) {
                this.$delete(this.draft.order.orderAdjustments, key)
                this.recalculateOrder(this.draft)
            },
        },
    }
</script>

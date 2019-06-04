<template>
    <div>
        <!-- Header -->
        <div class="text-right">
            <div v-if="$root.recalculateLoading" class="spinner"></div>
            <a class="btn" @click.prevent="autoRecalculate()">Recalculate</a>
        </div>

        <hr>

        <!-- Order details -->
        <div class="order-details" :class="{'order-opacity-50': $root.recalculateLoading || $root.saveLoading}">
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
                                @change="$root.recalculateOrder(draft)"
                        ></adjustments>
                    </div>
                </div>

                <hr />

                <!-- Total Price -->
                <div class="text-right">
                    <h2>{{ "Total Price" }}</h2>
                    <h2>{{ draft.order.totalPriceAsCurrency }}</h2>
                </div>

                <template v-if="$root.editing">
                    <hr>

                    <add-line-item
                            :order-id="$root.orderId"
                            @change="$root.recalculateOrder(draft)"
                    ></add-line-item>
                </template>
            </template>
        </div>

        <!-- Errors -->
        <template v-if="draft && draft.order.errors">
            <pre>{{draft.order.errors}}</pre>
        </template>
    </div>
</template>

<style lang="scss">
    @import '../sass/order-details.scss';
</style>

<script>
    import orderApi from './api/order'

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
            draft: {
                get() {
                    return this.$root.draft
                },
                set(newVal) {
                    this.$root.draft = newVal
                }
            },
        },

        methods: {
            autoRecalculate() {
                const draft = JSON.parse(JSON.stringify(this.draft))
                draft.order.recalculationMode = 'all'
                this.$root.recalculateOrder(draft)
            },

            removeAdjustment(key) {
                this.$delete(this.draft.order.orderAdjustments, key)
                this.$root.recalculateOrder(this.draft)
            },
        },
    }
</script>

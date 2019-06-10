<template>
    <div class="order-flex">
        <div class="order-block-title">
            <h3>Adjustments</h3>
        </div>

        <div class="order-flex-grow">
            <adjustments
                    :editing="editing"
                    :adjustments="adjustments"
                    :recalculation-mode="recalculationMode"
                    @addAdjustment="addOrderAdjustment"
                    @updateAdjustment="updateOrderAdjustment"
                    @removeAdjustment="removeOrderAdjustment"
            ></adjustments>
        </div>
    </div>
</template>

<script>
    import {mapGetters, mapActions} from 'vuex'
    import Adjustments from './Adjustments'

    export default {
        components: {
            Adjustments,
        },

        props: {
            recalculationMode: {
                type: String,
            },
            editing: {
                type: Boolean,
            },
            adjustments: {
                type: Array,
            },
        },

        computed: {
            ...mapGetters([
                'orderId',
            ]),
        },

        methods: {
            addOrderAdjustment() {
                const adjustment = {
                    id: null,
                    type: 'tax',
                    name: '',
                    description: '',
                    amount: '0.0000',
                    included: '0',
                    orderId: this.orderId,
                }

                const adjustments = this.adjustments

                adjustments.push(adjustment)

                this.$emit('updateOrderAdjustments', adjustments)
            },

            updateOrderAdjustment(adjustment, key) {
                const adjustments = this.adjustments
                adjustments[key] = adjustment
                this.$emit('updateOrderAdjustments', adjustments)
            },

            removeOrderAdjustment(key) {
                const adjustments = this.adjustments
                adjustments.splice(key, 1)
                this.$emit('updateOrderAdjustments', adjustments)
            },
        },
    }
</script>
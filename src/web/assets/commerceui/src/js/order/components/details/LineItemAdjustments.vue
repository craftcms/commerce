<template>
    <order-block v-if="editing || lineItem.adjustments.length" class="order-flex">
        <adjustments
                :editing="editing"
                :error-prefix="errorPrefix"
                :adjustments="adjustments"
                :recalculation-mode="recalculationMode"
                @addAdjustment="addAdjustment"
                @updateAdjustment="updateAdjustment"
                @removeAdjustment="removeAdjustment"
        ></adjustments>
    </order-block>
</template>

<script>
    import Adjustments from './Adjustments'

    export default {
        components: {
            Adjustments,
        },

        props: {
            lineItem: {
                type: Object,
            },
            editing: {
                type: Boolean,
            },
            recalculationMode: {
                type: String,
            },
            errorPrefix: {
                type: String,
            },
            orderId: {
                type: Number,
            },
        },

        computed: {
            adjustments() {
                return this.lineItem.adjustments
            },
        },

        methods: {
            addAdjustment() {
                const adjustment = {
                    id: null,
                    type: 'tax',
                    name: '',
                    description: '',
                    amount: '0.0000',
                    included: false,
                    orderId: this.orderId,
                    lineItemId: this.lineItem.id
                }

                const lineItem = this.lineItem

                lineItem.adjustments.push(adjustment)

                this.$emit('updateLineItem', lineItem)
            },

            updateAdjustment({adjustment, key}) {
                const lineItem = this.lineItem
                lineItem.adjustments[key] = adjustment
                this.$emit('updateLineItem', lineItem)
            },

            removeAdjustment(key) {
                const lineItem = this.lineItem
                lineItem.adjustments.splice(key, 1)
                this.$emit('updateLineItem', lineItem)
            },
        }
    }
</script>

<template>
    <div>
        <template v-for="(adjustment, key) in adjustments">
            <adjustment
                    :error-prefix="errorPrefix"
                    :key="key"
                    :adjustment="adjustment"
                    :adjustment-key="key"
                    :recalculation-mode="recalculationMode"
                    :editing="editing"
                    @update="updateAdjustment($event, key)"
                    @remove="$emit('removeAdjustment', key)"
            ></adjustment>
        </template>

        <template v-if="editing && recalculationMode === 'none'">
            <div class="adjustment-actions">
                <a @click.prevent="addAdjustment()">Add an adjustment</a>
            </div>
        </template>
    </div>
</template>

<script>
    import Adjustment from './Adjustment'

    export default {
        components: {
            Adjustment
        },

        props: {
            adjustments: {
                type: Array,
            },
            errorPrefix: {
                type: String,
            },
            recalculationMode: {
                type: String,
            },
            orderId: {
                type: Number,
            },
            editing: {
                type: Boolean,
            }
        },

        methods: {
            addAdjustment() {
                const adjustment = {
                    id: null,
                    type: 'tax',
                    name: '',
                    description: '',
                    amount: '0.0000',
                    included: '0',
                    orderId: this.orderId,
                }

                this.adjustments.push(adjustment)
                this.$emit('change')
            },

            updateAdjustment(adjustment, adjustmentKey) {
                const adjustments = JSON.parse(JSON.stringify(this.adjustments))
                adjustments[adjustmentKey] = adjustment
                this.$emit('updateAdjustments', adjustments)
            }
        }
    }
</script>
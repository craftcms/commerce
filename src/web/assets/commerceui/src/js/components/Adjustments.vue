<template>
    <div>
        <template v-for="(adjustment, key) in adjustments">
            <adjustment
                    :error-prefix="errorPrefix"
                    :key="key"
                    :adjustment="adjustment"
                    :adjustment-key="key"
                    :editing="editing"
                    :recalculation-mode="recalculationMode"
                    :adjustments="adjustments"
                    @change="$emit('change')"
                    @remove="removeAdjustment(key)"
            ></adjustment>
        </template>

        <template v-if="editing && recalculationMode === 'none'">
            <div>
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
            editing: {
                type: Boolean,
            },
            recalculationMode: {
                type: String,
            },
            errorPrefix: {
                type: String,
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
                    included: '0',
                    orderId: this.$root.draft.order.id,
                }

                this.adjustments.push(adjustment)
                this.$emit('change')
            },

            removeAdjustment(key) {
                this.$delete(this.adjustments, key)
                this.$emit('change')
            },
        }
    }
</script>
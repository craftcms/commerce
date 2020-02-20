<template>
    <div>
        <template v-for="(lineItem, lineItemKey) in lineItems">
            <line-item
                    :recalculation-mode="recalculationMode"
                    :key="lineItemKey"
                    :line-item="lineItem"
                    :line-item-key="lineItemKey"
                    :editing="editing"
                    @updateLineItem="updateLineItem($event, lineItemKey)"
                    @removeLineItem="removeLineItem(lineItemKey)"></line-item>
        </template>
    </div>
</template>

<script>
    import LineItem from './LineItem'

    export default {
        components: {
            LineItem
        },

        props: {
            recalculationMode: {
                type: String,
            },
            editing: {
                type: Boolean,
            },
            lineItems: {
                type: Array,
            }
        },

        methods: {
            updateLineItem(lineItem, lineItemKey) {
                const lineItems = this.lineItems
                lineItems[lineItemKey] = lineItem
                this.$emit('updateLineItems', lineItems)
            },

            addLineItem(lineItem) {
                const lineItems = this.lineItems
                lineItems.push(lineItem)
                this.$emit('updateLineItems')
            },

            removeLineItem(key) {
                const lineItems = this.lineItems
                lineItems.splice(key, 1)
                this.$emit('updateLineItems', lineItems)
            }
        },
    }
</script>

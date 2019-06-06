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
                    @remove="removeLineItem(lineItemKey)"></line-item>
        </template>
    </div>
</template>

<script>
    import {mapActions} from 'vuex'
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
            },
        },

        methods: {
            ...mapActions([
                'recalculateOrder',
                'removeLineItem',
            ]),

            updateLineItem(lineItem, lineItemKey) {
                const lineItems = JSON.parse(JSON.stringify(this.lineItems))
                lineItems[lineItemKey] = lineItem
                this.$emit('updateLineItems', lineItems)
            }
        },
    }
</script>
<template>
    <div>
        <template v-for="(lineItem, lineItemKey) in draft.order.lineItems">
            <line-item
                    :recalculation-mode="recalculationMode"
                    :key="lineItemKey"
                    :line-item="lineItem"
                    :line-item-key="lineItemKey"
                    :editing="editing"
                    @updateLineItem="updateLineItem($event, lineItemKey)"
                    @change="recalculateOrder(draft)"
                    @remove="removeLineItem(lineItemKey)"></line-item>
        </template>
    </div>
</template>

<script>
    import {mapState, mapActions} from 'vuex'
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
        },

        computed: {
            ...mapState({
                draft: state => state.draft,
            }),
        },

        methods: {
            ...mapActions([
                'recalculateOrder',
            ]),

            removeLineItem(lineItemKey) {
                this.$delete(this.draft.order.lineItems, lineItemKey)
                this.recalculateOrder(this.draft)
            },

            updateLineItem(lineItem, lineItemKey) {
                const lineItems = JSON.parse(JSON.stringify(this.draft.order.lineItems))
                lineItems[lineItemKey] = lineItem
                this.$emit('updateLineItems', lineItems)
            }
        },
    }
</script>
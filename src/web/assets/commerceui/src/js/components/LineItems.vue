<template>
    <div>
        <template v-for="(lineItem, lineItemKey) in draft.order.lineItems">
            <line-item
                    :key="lineItemKey"
                    :draft="draft"
                    :line-item="lineItem"
                    :line-item-key="lineItemKey"
                    :recalculation-mode="draft.order.recalculationMode"
                    @change="$root.recalculateOrder(draft)"
                    @remove="removeLineItem(lineItemKey)"></line-item>
        </template>
    </div>
</template>

<script>
    import LineItem from './LineItem'

    export default {
        components: {
            LineItem
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
            removeLineItem(lineItemKey) {
                this.$delete(this.draft.order.lineItems, lineItemKey)
                this.$root.recalculateOrder(this.draft)
            },
        }
    }
</script>
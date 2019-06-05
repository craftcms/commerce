<template>
    <div>
        <template v-for="(lineItem, lineItemKey) in draft.order.lineItems">
            <line-item
                    :key="lineItemKey"
                    :line-item="lineItem"
                    :line-item-key="lineItemKey"
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
        },
    }
</script>
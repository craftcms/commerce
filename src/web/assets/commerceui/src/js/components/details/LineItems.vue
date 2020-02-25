<template>
    <div>
        <div class="order-flex order-box-sizing">
            <div class="line-items-heading orderedit-border-color w-1/4"><h4 class="extralight">{{$options.filters.t('Item', 'commerce')}}</h4></div>
            <div class="line-items-heading orderedit-border-color w-1/4"><h4 class="extralight">{{$options.filters.t('Unit Price', 'commerce')}}</h4></div>
            <div class="line-items-heading orderedit-border-color w-1/4"><h4 class="extralight">{{$options.filters.t('Quantity', 'commerce')}}</h4></div>
            <div class="line-items-heading orderedit-border-color text-right order-flex-grow"><h4 class="extralight">{{$options.filters.t('Total', 'commerce')}}</h4></div>
        </div>
        <template v-for="(lineItem, lineItemKey) in lineItems">
            <line-item
                    :recalculation-mode="recalculationMode"
                    :key="lineItemKey"
                    :line-item="lineItem"
                    :line-item-key="lineItemKey"
                    :editing="editing"
                    @updateLineItem="updateLineItem($event, lineItemKey)"
                    @removeLineItem="removeLineItem(lineItemKey)"></line-item>
            <hr v-bind:key="lineItemKey" v-if="editing || lineItemKey != (lineItems.length -1)">
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

<style lang="scss">
    .line-items-heading {
        margin-top: 0;
        border-top: 1px solid;
        padding-bottom: 14px;
        padding-top: 14px;
    }
</style>
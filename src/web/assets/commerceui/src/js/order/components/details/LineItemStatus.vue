<template>
    <div>
        <template v-if="editing && lineItemStatuses.length > 0">
            <line-item-status-input :lineItem="lineItem" :editing="editing" @change="$emit('change', $event)"></line-item-status-input>
        </template>

        <template v-else>
            <span class="status" :class="{[lineItemStatus.color]: true}" v-if="lineItemStatus.color"></span>
            <span class="status" v-else></span>
            {{lineItemStatus.name}}
        </template>
    </div>
</template>

<script>
    import {mapGetters} from 'vuex'
    import LineItemStatusInput from './LineItemStatusInput'

    export default {
        components: {
            LineItemStatusInput
        },

        props: {
            lineItem: {
                type: Object,
            },
            editing: {
                type: Boolean,
            },
        },

        computed: {
            ...mapGetters([
                'lineItemStatuses',
            ]),

            lineItemStatusId() {
                return this.lineItem.lineItemStatusId
            },

            lineItemStatus() {
                if (this.lineItemStatusId !== 0) {
                    for (let lineItemStatusesKey in this.lineItemStatuses) {
                        const lineItemStatus = this.lineItemStatuses[lineItemStatusesKey]

                        if (lineItemStatus.id === this.lineItemStatusId) {
                            return lineItemStatus
                        }
                    }
                }

                return {id: 0, name: this.$options.filters.t("None", 'commerce'), color: null}
            },
        },
    }
</script>

<template>
    <div>
        <a class="btn menubtn" ref="lineItemStatus">
            <template v-if="lineItemStatus.color">
                <span class="status" :class="{[lineItemStatus.color]: true}"></span>
            </template>
            <template v-else>
                <span class="status"></span>
            </template>

            {{lineItemStatus.name}}
        </a>
        <div class="menu">
            <ul class="padded" role="listbox">
                <li>
                    <a data-id="0" data-name="None">
                        <span class="status"></span>
                        None
                    </a>
                </li>
                <li v-for="(status) in $root.lineItemStatuses">
                    <a
                            :data-id="status.id"
                            :data-color="status.color"
                            :data-name="status.name"
                            :class="{sel: lineItemStatus.id === status.value}">
                        <span class="status" :class="{[status.color]: true}"></span>
                        {{status.name}}
                    </a>
                </li>
            </ul>
        </div>
    </div>
</template>

<script>
    export default {

        props: {
            lineItem: {
                type: Object,
            },
        },

        computed: {
            lineItemStatus() {
                if (this.lineItem.lineItemStatusId !== 0) {
                    for (let lineItemStatusesKey in this.$root.lineItemStatuses) {
                        const lineItemStatus = this.$root.lineItemStatuses[lineItemStatusesKey]

                        if (lineItemStatus.id === this.lineItem.lineItemStatusId) {
                            return lineItemStatus
                        }
                    }
                }

                return {id: 0, name: "None", color: null}
            },
        },

        methods: {
            onSelectStatus(status) {
                if (status.dataset.id === 0) {
                    this.lineItem.lineItemStatusId = null
                } else {
                    this.lineItem.lineItemStatusId = parseInt(status.dataset.id)
                }
                
                this.$emit('change')
            },
        },

        mounted() {
            new Garnish.MenuBtn(this.$refs.lineItemStatus, {
                onOptionSelect: this.onSelectStatus
            })
        }
    }
</script>
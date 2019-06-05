<template>
    <div>
        <a class="btn menubtn" ref="orderStatus">
            <template v-if="orderStatus.color">
                <span class="status" :class="{[orderStatus.color]: true}"></span>
            </template>
            <template v-else>
                <span class="status"></span>
            </template>

            {{orderStatus.name}}
        </a>

        <div class="menu">
            <ul class="padded" role="listbox">
                <li v-for="(status) in $root.orderStatuses">
                    <a
                            :data-id="status.id"
                            :data-color="status.color"
                            :data-name="status.name"
                            :class="{sel: orderStatus.id === status.value}">
                        <span class="status" :class="{[status.color]: true}"></span>
                        {{status.name}}
                    </a>
                </li>
            </ul>
        </div>
    </div>
</template>

<script>
    /* global Garnish */

    export default {

        props: {
            order: {
                type: Object,
            },
        },

        computed: {
            orderStatus() {
                if (this.order.orderStatusId !== 0) {
                    for (let orderStatusesKey in this.$root.orderStatuses) {
                        const orderStatus = this.$root.orderStatuses[orderStatusesKey]

                        if (orderStatus.id === this.order.orderStatusId) {
                            return orderStatus
                        }
                    }
                }

                return {id: 0, name: "None", color: null}
            },
        },

        methods: {
            onSelectStatus(status) {
                if (status.dataset.id === 0) {
                    this.order.orderStatusId = null
                } else {
                    this.order.orderStatusId = parseInt(status.dataset.id)
                }

                this.$root.save()
            },
        },

        mounted() {
            new Garnish.MenuBtn(this.$refs.orderStatus, {
                onOptionSelect: this.onSelectStatus
            })
        }
    }
</script>
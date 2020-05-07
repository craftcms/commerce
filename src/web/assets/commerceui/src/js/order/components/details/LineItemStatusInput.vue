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
                    <a data-id="0" :data-name="$options.filters.t('None', 'commerce')">
                        <span class="status"></span>
                        {{"None"|t('commerce')}}
                    </a>
                </li>
                <li v-for="(status, key) in lineItemStatuses" :key="key">
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
    /* global Garnish */

    import {mapGetters} from 'vuex'

    export default {

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

            lineItemStatusId: {
                get() {
                    return this.lineItem.lineItemStatusId
                },

                set(val) {
                    this.$emit('change', val)
                }
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

        methods: {
            onSelectStatus(status) {
                const id = status.dataset.id

                if (status.dataset.id == 0) {
                    this.lineItemStatusId = null
                } else {
                    this.lineItemStatusId = parseInt(id)
                }
            },
        },

        mounted() {
            new Garnish.MenuBtn(this.$refs.lineItemStatus, {
                onOptionSelect: this.onSelectStatus
            })
        }
    }
</script>

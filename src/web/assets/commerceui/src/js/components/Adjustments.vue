<template>
    <div>
        <template v-for="(adjustment, key) in adjustments">
            <adjustment
                    :error-prefix="errorPrefix"
                    :key="key"
                    :adjustment="adjustment"
                    :adjustment-key="key"
                    :adjustments="adjustments"
                    @change="$emit('change')"
                    @remove="removeAdjustment(key)"
            ></adjustment>
        </template>

        <template v-if="editing && draft.order.recalculationMode === 'none'">
            <div class="adjustment-actions">
                <a @click.prevent="addAdjustment()">Add an adjustment</a>
            </div>
        </template>
    </div>
</template>

<script>
    import {mapState} from 'vuex'
    import Adjustment from './Adjustment'

    export default {
        components: {
            Adjustment
        },

        props: {
            adjustments: {
                type: Array,
            },
            errorPrefix: {
                type: String,
            },
        },


        computed: {
            ...mapState({
                draft: state => state.draft,
                editing: state => state.editing,
            }),
        },
        methods: {
            addAdjustment() {
                const adjustment = {
                    id: null,
                    type: 'tax',
                    name: '',
                    description: '',
                    amount: '0.0000',
                    included: '0',
                    orderId: this.draft.order.id,
                }

                this.adjustments.push(adjustment)
                this.$emit('change')
            },

            removeAdjustment(key) {
                this.$delete(this.adjustments, key)
                this.$emit('change')
            },
        }
    }
</script>
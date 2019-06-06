<template>
    <div class="order-flex">
        <div class="order-block-title">
            <h3>Adjustments</h3>
        </div>

        <div class="order-flex-grow">
            <adjustments
                    :adjustments="orderAdjustments"
                    @updateAdjustments="updateAdjustments"
                    @removeAdjustment="removeOrderAdjustment"
                    @change="recalculateOrder(draft)"
            ></adjustments>
        </div>
    </div>
</template>

<script>
    import {mapState, mapActions} from 'vuex'
    import Adjustments from './Adjustments'

    export default {
        components: {
            Adjustments,
        },

        computed: {
            orderAdjustments: {
                get() {
                    return JSON.parse(JSON.stringify(this.draft.order.orderAdjustments))
                },

                set(adjustments) {
                    const draft = JSON.parse(JSON.stringify(this.draft))
                    draft.order.orderAdjustments = adjustments
                    this.$store.commit('updateDraft', draft)
                    this.recalculateOrder(this.draft)
                }
            },

            ...mapState({
                draft: state => state.draft,
            }),
        },

        methods: {
            ...mapActions([
                'recalculateOrder',
                'removeOrderAdjustment',
            ]),

            updateAdjustments(adjustments) {
                this.orderAdjustments = adjustments
            }
        },
    }
</script>
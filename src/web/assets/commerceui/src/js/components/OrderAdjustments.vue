<template>
    <div class="order-flex">
        <div class="order-block-title">
            <h3>Adjustments</h3>
        </div>

        <div class="order-flex-grow">
            <adjustments
                    :adjustments="draft.order.orderAdjustments"
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
            ...mapState({
                draft: state => state.draft,
            }),
        },

        methods: {
            ...mapActions([
                'recalculateOrder',
                'removeOrderAdjustment',
            ]),
        },

    }
</script>
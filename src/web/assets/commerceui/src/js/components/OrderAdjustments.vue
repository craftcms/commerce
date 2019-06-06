<template>
    <div class="order-flex">
        <div class="order-block-title">
            <h3>Adjustments</h3>
        </div>

        <div class="order-flex-grow">
            <adjustments
                    :editing="editing"
                    :adjustments="adjustments"
                    :recalculation-mode="recalculationMode"
                    @updateAdjustment="updateOrderAdjustment"
                    @removeAdjustment="removeOrderAdjustment"
            ></adjustments>
        </div>
    </div>
</template>

<script>
    import {mapActions} from 'vuex'
    import Adjustments from './Adjustments'

    export default {
        components: {
            Adjustments,
        },

        props: {
            recalculationMode: {
                type: String,
            },
            editing: {
                type: Boolean,
            },
            adjustments: {
                type: Array,
            },
        },

        methods: {
            ...mapActions([
                'recalculateOrder',
                'updateOrderAdjustment',
                'removeOrderAdjustment',
            ]),
        },
    }
</script>
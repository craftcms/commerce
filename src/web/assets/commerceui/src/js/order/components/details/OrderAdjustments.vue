<template>
    <div class="order-flex justify-end">
        <div class="w-1/4">
            <btn-link @click="enableEditMode()" v-if="!editMode && draft.order.isCompleted">{{'Edit'|t('commerce')}}</btn-link>
        </div>
        <div class="w-3/4">
            <adjustments
                    :editing="editing && editMode"
                    :adjustments="adjustments"
                    :recalculation-mode="recalculationMode"
                    @addAdjustment="addOrderAdjustment"
                    @updateAdjustment="updateOrderAdjustment"
                    @removeAdjustment="removeOrderAdjustment"
            ></adjustments>
        </div>
    </div>
</template>

<script>
    import {mapActions, mapGetters, mapState} from 'vuex'
    import Adjustments from './Adjustments'
    import BtnLink from '../../../base/components/BtnLink';

    export default {
        components: {
            Adjustments,
            BtnLink,
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

        data() {
            return {
                editMode: false,
            };
        },

        computed: {
            ...mapGetters([
                'orderId',
            ]),

            ...mapState({
                draft: state => state.draft,
            }),
        },

        methods: {
            ...mapActions([
                'edit',
            ]),

            addOrderAdjustment() {
                const adjustment = {
                    id: null,
                    type: 'tax',
                    name: '',
                    description: '',
                    amount: '0.0000',
                    included: '0',
                    orderId: this.orderId,
                }

                const adjustments = this.adjustments

                adjustments.push(adjustment)

                this.$emit('updateOrderAdjustments', adjustments)
            },

            enableEditMode() {
                this.editMode = true;
                this.edit();
            },

            updateOrderAdjustment(adjustment, key) {
                const adjustments = this.adjustments
                adjustments[key] = adjustment
                this.$emit('updateOrderAdjustments', adjustments)
            },

            removeOrderAdjustment(key) {
                const adjustments = this.adjustments
                adjustments.splice(key, 1)
                this.$emit('updateOrderAdjustments', adjustments)
            },
        },
    }
</script>

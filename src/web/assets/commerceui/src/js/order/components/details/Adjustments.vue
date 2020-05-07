<template>
    <div class="w-full">
        <template v-for="(adjustment, key) in adjustments">
            <adjustment
                    :show-labels="key === 0 ? true : false"
                    :error-prefix="errorPrefix"
                    :key="key"
                    :adjustment="adjustment"
                    :adjustment-key="key"
                    :recalculation-mode="recalculationMode"
                    :editing="editing"
                    @update="$emit('updateAdjustment', {adjustment: $event, key})"
                    @remove="$emit('removeAdjustment', key)"
            ></adjustment>
        </template>

        <template v-if="editing && recalculationMode === 'none'">
            <div class="adjustment-actions">
                <btn-link button-class="btn add icon" @click="$emit('addAdjustment')">{{"Add an adjustment"|t('commerce')}}</btn-link>
            </div>
        </template>
    </div>
</template>

<script>
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
            recalculationMode: {
                type: String,
            },
            orderId: {
                type: Number,
            },
            editing: {
                type: Boolean,
            }
        },
    }
</script>

<style lang="scss">
    .adjustment-actions {
        padding: 14px 0;
    }
</style>

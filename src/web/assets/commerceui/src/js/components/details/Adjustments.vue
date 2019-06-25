<template>
    <div>
        <template v-for="(adjustment, key) in adjustments">
            <adjustment
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
                <a @click.prevent="$emit('addAdjustment')">{{"Add an adjustment"|t('commerce')}}</a>
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
<template>
    <div class="order-flex" :key="'adjustment-'+adjustmentKey">
        <div class="order-flex-grow">
            <div>
                <template v-if="editing">
                    <div>
                        <label>Type</label>
                        <select v-model="adjustment.type">
                            <option v-for="adjustmentOption in adjustmentOptions" :value="adjustmentOption.value">
                                {{adjustmentOption.label}}
                            </option>
                        </select>
                    </div>
                    <div>
                        <label>Name</label>
                        <input type="text" v-model="adjustment.name" @input="$emit('change')" />
                    </div>
                    <div>
                        <label>Description</label>
                        <input type="text" v-model="adjustment.description" @input="$emit('change')" />
                    </div>
                    <div>
                        <label>Amount</label>
                        <input type="text" v-model="adjustment.amount" @input="$emit('change')" />
                    </div>
                    <div>
                        <label>Included</label>
                        <input type="checkbox" v-model="included" @input="$emit('change')" />
                    </div>
                </template>
                <template v-else>
                    {{adjustment.name}}
                    <span class="light">({{adjustment.type}})</span>
                    {{adjustment.description}}
                </template>

                <template v-if="editing && recalculationMode === 'none'">
                    <a @click.prevent="removeAdjustment(adjustmentKey)">Remove</a>
                    <hr>
                </template>
            </div>
        </div>
        <div class="order-flex-grow text-right">
            <template v-if="adjustment.included !== '0' && adjustment.included !== false">
                <div class="light">{{adjustment.amountAsCurrency}} included</div>
            </template>
            <template v-else>
                {{adjustment.amountAsCurrency}}
            </template>
        </div>
    </div>
</template>

<script>
    export default {
        props: {
            adjustmentKey: {
                type: Number,
            },
            adjustment: {
                type: Object,
            },
            editing: {
                type: Boolean,
            },
            recalculationMode: {
                type: String,
            },
        },

        data() {
            return {
                adjustmentOptions: [
                    {
                        label: 'Tax',
                        value: 'tax',
                    },
                    {
                        label: 'Discount',
                        value: 'discount',
                    },
                    {
                        label: 'Shipping',
                        value: 'shipping',
                    },
                ],
            }
        },

        computed: {
            included: {
                get() {
                    if (this.adjustment.included === '1') {
                        return true
                    }

                    return false
                },
                set(newValue) {
                    this.adjustment.included = (newValue ? '1' : '0')
                }
            },

        }
    }
</script>
<template>
    <div class="order-flex">
        <div class="order-flex-grow">
            <div>
                <template v-if="$root.editing">
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
                        <input-error :error-key="errorPrefix+adjustmentKey+'.amount'"></input-error>
                    </div>
                    <div>
                        <label>Included</label>
                        <input type="checkbox" v-model="included" @input="$emit('change')" />
                        <input-error :error-key="errorPrefix+adjustmentKey+'.included'"></input-error>
                    </div>
                </template>
                <template v-else>
                    {{adjustment.name}}
                    <span class="light">({{adjustment.type}})</span>
                    {{adjustment.description}}
                    <div>
                        <template v-if="!showSnapshot">
                            <a @click.prevent="showSnapshot = true">Show snapshot</a>
                        </template>
                        <template v-else>
                            <a @click.prevent="showSnapshot = false">Hide snapshot</a>
                            <div>
                                <pre><code>{{adjustment.sourceSnapshot}}</code></pre>
                            </div>
                        </template>
                    </div>
                </template>

                <template v-if="$root.editing && $root.draft.order.recalculationMode === 'none'">
                    <a @click.prevent="$emit('remove')">Remove</a>
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
    import InputError from './InputError'

    export default {
        components: {
            InputError,
        },

        props: {
            adjustment: {
                type: Object,
            },
            adjustmentKey: {
                type: Number,
            },
            lineItemKey: {
                type: Number,
            },
            adjustments: {
                type: Array
            },
            errorPrefix: {
                type: String,
            }
        },

        data() {
            return {
                showSnapshot: false,
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
        },
    }
</script>
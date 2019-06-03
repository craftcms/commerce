<template>
    <div class="order-flex">
        <div class="order-flex-grow">
            <div>
                <template v-if="$root.editing">
                    <div class="adjustment-field">
                        <field label="Type">
                            <div class="select">
                                <select v-model="adjustment.type">
                                    <option v-for="adjustmentOption in adjustmentOptions" :value="adjustmentOption.value">
                                        {{adjustmentOption.label}}
                                    </option>
                                </select>
                            </div>
                        </field>
                    </div>
                    <div class="adjustment-field">
                        <field label="Name">
                            <input type="text" class="text" v-model="adjustment.name" @input="$emit('change')" />
                        </field>
                    </div>
                    <div class="adjustment-field">
                        <field label="Description">
                            <input type="text" class="text" v-model="adjustment.description" @input="$emit('change')" />
                        </field>
                    </div>
                    <div class="adjustment-field">
                        <field label="Amount" :errors="$root.getErrors(errorPrefix+adjustmentKey+'.amount')">
                            <input type="text" class="text" :class="{error: $root.getErrors(errorPrefix+adjustmentKey+'.amount').length}" v-model="adjustment.amount" @input="$emit('change')" />
                        </field>
                    </div>
                    <div class="adjustment-field">
                        <field label="Included" :errors="$root.getErrors(errorPrefix+adjustmentKey+'.included')">
                            <input type="checkbox" v-model="included" @input="$emit('change')" />
                        </field>
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
    import Field from './Field'

    export default {
        components: {
            Field,
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
                    if (this.adjustment.included === true || this.adjustment === '1') {
                        return true
                    }

                    return false
                },
                set(newValue) {
                    this.adjustment.included = newValue
                }
            },
        },
    }
</script>

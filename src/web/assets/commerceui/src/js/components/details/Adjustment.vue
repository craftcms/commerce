<template>
    <div class="adjustment order-flex">
        <div class="order-flex-grow">
            <div>
                <template v-if="editing">
                    <div class="meta">
                        <field label="Type" :required="true">
                            <div class="select">
                                <select v-model="type">
                                    <option v-for="adjustmentOption in adjustmentOptions" :value="adjustmentOption.value">
                                        {{adjustmentOption.label}}
                                    </option>
                                </select>
                            </div>
                        </field>

                        <field label="Name">
                            <input type="text" class="text" v-model="name" />

                        </field>

                        <field label="Description">
                            <input type="text" class="text" v-model="description" />
                        </field>

                        <field label="Amount" :required="true" :errors="[...getErrors(errorPrefix+adjustmentKey+'.amount'), ...getErrors(errorPrefix+adjustmentKey+'.included')]">
                            <div class="flex">
                                <div class="textwrapper">
                                    <input type="text" class="text" v-model="amount" :class="{error: getErrors(errorPrefix+adjustmentKey+'.amount').length}" />
                                </div>
                                <div class="nowrap">
                                    <input :id="_uid + '-included'" type="checkbox" class="checkbox" v-model="included" /> <label :for="_uid + '-included'">Included</label>
                                </div>
                            </div>
                        </field>
                    </div>
                </template>
                <template v-else>
                    {{name}}
                    <span class="light">({{type}})</span>
                    {{description}}
                    <div>
                        <template v-if="!showSnapshot">
                            <a @click.prevent="showSnapshot = true">Snapshot <i data-icon="downangle"></i></a>
                        </template>
                        <template v-else>
                            <a @click.prevent="showSnapshot = false">Hide snapshot <i data-icon="upangle"></i></a>
                            <div>
                                <pre><code>{{adjustment.sourceSnapshot}}</code></pre>
                            </div>
                        </template>
                    </div>
                </template>

                <template v-if="editing && recalculationMode === 'none'">
                    <div class="adjustment-actions">
                        <a @click.prevent="$emit('remove')">Remove</a>
                    </div>
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
    import {debounce} from 'debounce'
    import {mapGetters} from 'vuex'
    import Field from '../Field'

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
            errorPrefix: {
                type: String,
            },
            recalculationMode: {
                type: String,
            },
            editing: {
                type: Boolean,
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
            ...mapGetters([
                'getErrors',
            ]),

            type: {
                get() {
                    return this.adjustment.type
                },

                set(value) {
                    const adjustment = this.adjustment
                    adjustment.type = value
                    this.$emit('update', adjustment)
                }
            },

            name: {
                get() {
                    return this.adjustment.name
                },

                set: debounce(function(value) {
                    const adjustment = this.adjustment
                    adjustment.name = value
                    this.$emit('update', adjustment)
                }, 1000)
            },

            description: {
                get() {
                    return this.adjustment.description
                },

                set: debounce(function(value) {
                    const adjustment = this.adjustment
                    adjustment.description = value
                    this.$emit('update', adjustment)
                }, 1000)
            },

            amount: {
                get() {
                    return this.adjustment.amount
                },

                set: debounce(function(value) {
                    const adjustment = this.adjustment
                    adjustment.amount = value
                    this.$emit('update', adjustment)
                }, 1000)
            },

            included: {
                get() {
                    if (this.adjustment.included === true || this.adjustment.included === '1') {
                        return true
                    }

                    return false
                },
                set(value) {
                    const adjustment = this.adjustment
                    adjustment.included = value
                    this.$emit('update', adjustment)
                }
            },
        },
    }
</script>

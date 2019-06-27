<template>
    <div class="adjustment order-flex">
        <div class="order-flex-grow">
            <template v-if="editing && recalculationMode === 'none'">
                <div class="fields order-flex">
                    <field :label="$options.filters.t('Type', 'commerce')" :required="true">
                        <div class="select">
                            <select v-model="type">
                                <option v-for="(adjustmentOption, key) in adjustmentOptions" :value="adjustmentOption.value" :key="key">
                                    {{adjustmentOption.label}}
                                </option>
                            </select>
                        </div>
                    </field>

                    <field :label="$options.filters.t('Name', 'commerce')">
                        <input type="text" class="text" v-model="name" />
                    </field>

                    <field :label="$options.filters.t('Description', 'commerce')">
                        <input type="text" class="text" v-model="description" />
                    </field>

                    <field :label="$options.filters.t('Amount', 'commerce')" :required="true" :errors="[...getErrors(errorPrefix+adjustmentKey+'.amount'), ...getErrors(errorPrefix+adjustmentKey+'.included')]">
                        <input type="text" class="text" v-model="amount" :class="{error: getErrors(errorPrefix+adjustmentKey+'.amount').length}" />

                        <div class="included">
                            <input :id="_uid + '-included'" type="checkbox" class="checkbox" v-model="included" /> <label :for="_uid + '-included'">{{"Included"|t('commerce')}}</label>
                        </div>
                    </field>
                </div>
            </template>
            <template v-else>
                {{name}}
                <span class="light">({{type}})</span>
                {{description}}
                <div>
                    <btn-link @click="showSnapshot = !showSnapshot">
                        <template v-if="!showSnapshot">
                            {{"Snapshot"|t('commerce')}} <i data-icon="downangle"></i>
                        </template>
                        <template v-else>
                            {{"Hide snapshot"|t('commerce')}} <i data-icon="upangle"></i>
                        </template>
                    </btn-link>

                    <template v-if="showSnapshot">
                        <div>
                            <pre><code>{{adjustment.sourceSnapshot}}</code></pre>
                        </div>
                    </template>
                </div>
            </template>
        </div>

        <div class="order-flex-grow text-right order-price">
            <template v-if="adjustment.included !== '0' && adjustment.included !== false">
                <div class="light">{{"{amount} included"|t('commerce', {amount: adjustment.amountAsCurrency})}}</div>
            </template>
            <template v-else>
                {{adjustment.amountAsCurrency}}
            </template>

            <template v-if="editing && recalculationMode === 'none'">
                <btn-link @click="$emit('remove')">{{"Remove"|t('commerce')}}</btn-link>
            </template>
        </div>
    </div>
</template>

<script>
    import debounce from 'lodash.debounce'
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
                    if (this.adjustment.included === true || this.adjustment.included === '1') {
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

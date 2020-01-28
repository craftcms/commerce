<template>
    <div class="adjustment order-flex">
        <div class="order-flex-grow">
            <template v-if="editing && recalculationMode === 'none'">
                <div class="fields order-flex">
                    <field :label="$options.filters.t('Type', 'commerce')" :required="true" v-slot:default="slotProps">
                        <div class="select">
                            <select :id="slotProps.id" v-model="type">
                                <option v-for="(adjustmentOption, key) in adjustmentOptions" :value="adjustmentOption.value" :key="key">
                                    {{adjustmentOption.label}}
                                </option>
                            </select>
                        </div>
                    </field>

                    <field :label="$options.filters.t('Name', 'commerce')" v-slot:default="slotProps">
                        <input :id="slotProps.id" type="text" class="text" v-model="name" />
                    </field>

                    <field :label="$options.filters.t('Description', 'commerce')" v-slot:default="slotProps">
                        <input :id="slotProps.id" type="text" class="text" v-model="description" />
                    </field>

                    <field :label="$options.filters.t('Amount', 'commerce')" :required="true" :errors="[...getErrors(errorPrefix+adjustmentKey+'.amount'), ...getErrors(errorPrefix+adjustmentKey+'.included')]" v-slot:default="slotProps">
                        <input :id="slotProps.id" type="text" class="text" v-model="amount" :class="{error: getErrors(errorPrefix+adjustmentKey+'.amount').length}" />

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

                <snapshot>{{adjustment.sourceSnapshot}}</snapshot>
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
                <div>
                    <btn-link @click="$emit('remove')">{{"Remove"|t('commerce')}}</btn-link>
                </div>
            </template>
        </div>
    </div>
</template>

<script>
    import debounce from 'lodash.debounce'
    import {mapGetters} from 'vuex'
    import Field from '../Field'
    import Snapshot from './Snapshot'

    export default {
        components: {
            Field,
            Snapshot,
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

<style lang="scss">
    .adjustment {
        padding-bottom: 10px;
        padding-top: 10px;

        &:not(:last-child) {
            border-bottom: 1px solid rgba(0, 0, 20, 0.1);
        }

        &:first-child {
            padding-top: 0px;
        }

        .fields {
            display: flex;
            box-sizing: inherit;
            margin: 0 -10px;

            .field {
                margin: 0;
                width: 25%;
                padding: 0 10px;
                box-sizing: inherit;

                .input {
                    &::after {
                        display: none !important;
                    }

                    .select {
                        width: 100%;
                        box-sizing: border-box;

                        select {
                            width: 100%;
                        }
                    }

                    input[type="text"] {
                        width: 100%;
                    }

                    .included {
                        margin-top: 10px;
                    }
                }
            }
        }
    }

    .order-price {
        min-width: 160px;
    }
</style>

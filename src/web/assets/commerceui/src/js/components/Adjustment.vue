<template>
    <div class="adjustment order-flex">
        <div class="order-flex-grow">
            <div>
                <template v-if="editing">
                    <div class="meta">
                        <field label="Type" :required="true">
                            <div class="select">
                                <select v-model="adjustment.type">
                                    <option v-for="adjustmentOption in adjustmentOptions" :value="adjustmentOption.value">
                                        {{adjustmentOption.label}}
                                    </option>
                                </select>
                            </div>
                        </field>

                        <field label="Name">
                            <input type="text" class="text" v-model="adjustment.name" @input="$emit('change')" />
                        </field>

                        <field label="Description">
                            <input type="text" class="text" v-model="adjustment.description" @input="$emit('change')" />
                        </field>

                        <field label="Amount" :required="true" :errors="[...getErrors(errorPrefix+adjustmentKey+'.amount'), ...getErrors(errorPrefix+adjustmentKey+'.included')]">
                            <div class="flex">
                                <div class="textwrapper">
                                    <input type="text" class="text" :class="{error: getErrors(errorPrefix+adjustmentKey+'.amount').length}" v-model="adjustment.amount" @input="$emit('change')" />
                                </div>
                                <div class="nowrap">
                                    <input :id="_uid + '-included'" type="checkbox" class="checkbox" v-model="included" @input="$emit('change')" /> <label :for="_uid + '-included'">Included</label>
                                </div>
                            </div>
                        </field>
                    </div>
                </template>
                <template v-else>
                    {{adjustment.name}}
                    <span class="light">({{adjustment.type}})</span>
                    {{adjustment.description}}
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

                <template v-if="editing && draft.order.recalculationMode === 'none'">
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
    import {mapState, mapGetters} from 'vuex'
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
            ...mapState({
                draft: state => state.draft,
                editing: state => state.editing,
            }),

            ...mapGetters([
                'getErrors',
            ]),

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

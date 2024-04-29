<template>
    <div class="adjustment order-flex" :class="{'align-center': !showLabels}">
        <template v-if="editing && recalculationMode === 'none'">
            <div class="fields order-flex">
                <field
                    :label="
                        showLabels ? $options.filters.t('Type', 'commerce') : ''
                    "
                    :required="true"
                    v-slot:default="slotProps"
                >
                    <div class="select" v-if="isAllowedAdjustmentType">
                        <select :id="slotProps.id" v-model="type">
                            <option
                                v-for="(
                                    adjustmentOption, key
                                ) in adjustmentOptions"
                                :value="adjustmentOption.value"
                                :key="key"
                            >
                                {{ adjustmentOption.label }}
                            </option>
                        </select>
                    </div>
                    <div v-else>
                        <input
                            :id="slotProps.id"
                            type="text"
                            class="text readonly"
                            v-model="type"
                            readonly
                        />
                    </div>
                </field>

                <field
                    :label="
                        showLabels ? $options.filters.t('Name', 'commerce') : ''
                    "
                    v-slot:default="slotProps"
                >
                    <input
                        :id="slotProps.id"
                        type="text"
                        class="text"
                        :class="{readonly: !isAllowedAdjustmentType}"
                        v-model="name"
                        :readonly="!isAllowedAdjustmentType"
                    />
                </field>

                <field
                    :label="
                        showLabels
                            ? $options.filters.t('Description', 'commerce')
                            : ''
                    "
                    v-slot:default="slotProps"
                >
                    <input
                        :id="slotProps.id"
                        type="text"
                        class="text"
                        :class="{readonly: !isAllowedAdjustmentType}"
                        v-model="description"
                        :readonly="!isAllowedAdjustmentType"
                    />
                </field>

                <field
                    :label="
                        showLabels
                            ? $options.filters.t('Included', 'commerce')
                            : ''
                    "
                    v-slot:default="slotProps"
                    :class="{
                        'included-labels': showLabels,
                        included: !showLabels,
                        'order-flex': true,
                        'align-center': true,
                    }"
                >
                    <div v-if="isAllowedAdjustmentType">
                        <input
                            :id="slotProps.id"
                            type="checkbox"
                            class="checkbox"
                            v-model="included"
                        /><label :for="slotProps.id">&nbsp;</label>
                    </div>
                    <div v-else>
                        <input
                            :id="slotProps.id"
                            type="hidden"
                            class=""
                            :value="included ? '1' : '0'"
                        /><label :for="slotProps.id">&nbsp;</label>
                        <input
                            :id="slotProps.id"
                            type="checkbox"
                            class="checkbox readonly"
                            v-model="included"
                            :disabled="true"
                        /><label :for="slotProps.id">&nbsp;</label>
                    </div>
                </field>

                <field
                    :label="
                        showLabels
                            ? $options.filters.t('Amount', 'commerce')
                            : ''
                    "
                    :required="true"
                    :errors="[
                        ...getErrors(errorPrefix + adjustmentKey + '.amount'),
                        ...getErrors(errorPrefix + adjustmentKey + '.included'),
                    ]"
                    v-slot:default="slotProps"
                >
                    <input
                        :id="slotProps.id"
                        type="text"
                        class="text"
                        v-model="amount"
                        :placeholder="amount"
                        :class="{
                            error: hasAmountErrors,
                            readonly: !isAllowedAdjustmentType,
                        }"
                        :readonly="!isAllowedAdjustmentType"
                    />
                </field>
                <div
                    class="order-flex justify-center flex-grow"
                    :class="{pt: showLabels}"
                >
                    <btn-link
                        button-class="btn-link btn-link--danger icon delete"
                        @click="$emit('remove')"
                    ></btn-link>
                </div>
            </div>
        </template>
        <template v-else>
            <div class="w-1/5">
                <span class="adjustment-type">{{ type }}</span>
            </div>
            <div class="w-4/5 order-flex">
                <div class="w-2/3">
                    {{ name
                    }}<span v-if="description"> - {{ description }}</span>
                </div>
                <div class="w-1/3 text-right">
                    <template
                        v-if="
                            adjustment.included !== '0' &&
                            adjustment.included !== false
                        "
                    >
                        <div class="light">
                            {{
                                '{amount} included'
                                    | t('commerce', {
                                        amount: adjustment.amountAsCurrency,
                                    })
                            }}
                        </div>
                    </template>
                    <template v-else>
                        {{ adjustment.amountAsCurrency }}
                    </template>
                </div>
            </div>
        </template>
    </div>
</template>

<script>
    import debounce from 'lodash.debounce';
    import {mapGetters} from 'vuex';
    import Field from '../../../base/components/Field';

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
            },
            showLabels: {
                type: Boolean,
                default: false,
            },
        },

        data() {
            return {
                adjustmentOptions: [
                    {
                        label: this.$options.filters.t('Tax', 'commerce'),
                        value: 'tax',
                    },
                    {
                        label: this.$options.filters.t('Discount', 'commerce'),
                        value: 'discount',
                    },
                    {
                        label: this.$options.filters.t('Shipping', 'commerce'),
                        value: 'shipping',
                    },
                ],
                allowedAdjustmentTypes: ['tax', 'discount', 'shipping'],
                localAdjustmentAmount: this.adjustment.amount,
                amountNaN: false,
            };
        },

        computed: {
            ...mapGetters(['getErrors']),

            type: {
                get() {
                    return this.adjustment.type;
                },

                set(value) {
                    const adjustment = this.adjustment;
                    adjustment.type = value;
                    this.$emit('update', adjustment);
                },
            },

            name: {
                get() {
                    return this.adjustment.name;
                },

                set: debounce(function (value) {
                    const adjustment = this.adjustment;
                    adjustment.name = value;
                    this.$emit('update', adjustment);
                }, 1000),
            },

            description: {
                get() {
                    return this.adjustment.description;
                },

                set: debounce(function (value) {
                    const adjustment = this.adjustment;
                    adjustment.description = value;
                    this.$emit('update', adjustment);
                }, 1000),
            },

            amount: {
                get() {
                    return this.localAdjustmentAmount;
                },

                set: debounce(function (value) {
                    this.localAdjustmentAmount = value;

                    if (value === '' || isNaN(value)) {
                        if (value === '') {
                            this.amountNaN = false;
                        } else {
                            this.amountNaN = true;
                        }
                        return;
                    }

                    this.amountNaN = false;
                    let adjustment = this.adjustment;
                    adjustment.amount = this.localAdjustmentAmount;
                    this.$emit('update', adjustment);
                }, 1000),
            },

            hasAmountErrors() {
                return (
                    this.getErrors(
                        this.errorPrefix + this.adjustmentKey + '.amount'
                    ).length > 0 || this.amountNaN
                );
            },

            included: {
                get() {
                    if (
                        this.adjustment.included === true ||
                        this.adjustment.included === '1'
                    ) {
                        return true;
                    }

                    return false;
                },

                set(value) {
                    const adjustment = this.adjustment;
                    adjustment.included = value;
                    this.$emit('update', adjustment);
                },
            },

            isAllowedAdjustmentType() {
                return this.allowedAdjustmentTypes.indexOf(this.type) >= 0;
            },
        },
    };
</script>

<style lang="scss">
    @import 'craftcms-sass/mixins';

    .adjustment {
        padding-bottom: 10px;

        &-type {
            color: $lightTextColor;
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
        }

        .fields {
            display: flex;
            box-sizing: inherit;
            margin: 0 -5px;

            .field {
                margin: 0;
                width: 20%;
                padding: 0 5px;
                box-sizing: inherit;

                &.included,
                &.included-labels {
                    width: 14%;

                    .input {
                        display: flex;
                    }
                }

                &.included-labels {
                    display: flex;
                    flex-direction: column;

                    .input {
                        flex-grow: 1;
                        align-items: center;
                    }
                }

                &.included {
                    .input {
                        width: 100%;
                        justify-content: center;
                    }
                }

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

                    input[type='text'] {
                        width: 100%;
                    }

                    .readonly {
                        background-color: $grey200;
                        color: $mediumTextColor;
                    }
                }
            }
        }
    }

    .order-price {
        min-width: 160px;
    }
</style>

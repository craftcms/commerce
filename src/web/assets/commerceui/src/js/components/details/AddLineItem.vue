<template>
    <div>
        <template v-if="!showForm">
            <template v-if="lineItems.length > 0">
                <btn-link @click="handleShowForm">{{"Add a line item"|t('commerce')}}</btn-link>
            </template>
            <template v-else>
                <div class="starter">
                    <div data-icon="info"></div>
                    <h2>{{"Your order is empty"|t('commerce')}}</h2>
                    <btn-link @click="handleShowForm">{{"Create your first line item"|t('commerce')}}</btn-link>
                </div>
            </template>
        </template>
        <template v-else>
            <form @submit.prevent="lineItemAdd()" class="add-line-item-form">
                <select-input
                        label="sku"
                        v-model="selectedPurchasable"
                        :options="purchasables"
                        :disabled="formDisabled"
                        :filterable="false"
                        @search="onSearch">
                    <template v-slot:option="slotProps">
                        <div class="purchasable-select-option" v-bind:class="{ notAvailable: !slotProps.option.isAvailable }">
                            <div class="description">
                                <template v-if="slotProps.option.description">
                                    {{slotProps.option.description}}
                                    <template v-if="!slotProps.option.isAvailable"> ({{"Not available"|t('commerce')}})</template>
                                </template>
                                <template v-else>
                                    <em>{{"No description"|t('commerce')}}</em>
                                </template>
                            </div>
                            <div class="sku">{{ slotProps.option.sku }}</div>
                            <div class="price">{{ slotProps.option.priceAsCurrency }}</div>
                        </div>
                    </template>
                </select-input>

                <line-item-options-input
                    v-if="validPurchasable"
                    :config="lineItemOptionsConfig"
                    ref="lineItemOptions"
                    class="line-item-options"
                    v-on:validated="onLineItemOptionsValidated">
                </line-item-options-input>

                <ul v-if="lineItemOptionsErrors.length > 0" class="errors">
                    <li v-for="(error, key) in lineItemOptionsErrors" :key="key">{{error}}</li>
                </ul>

                <div class="buttons">
                    <input type="button" class="btn" :class="{disabled: formDisabled}" :disabled="formDisabled" :value="$options.filters.t('Cancel', 'commerce')" @click="handleHideForm" />
                    <input type="submit" class="btn submit" :class="{disabled: submitDisabled}" :disabled="submitDisabled" :value="$options.filters.t('Add', 'commerce')" />
                </div>
            </form>
        </template>
    </div>
</template>

<script>
    import {mapState, mapGetters, mapActions} from 'vuex'
    import debounce from 'lodash.debounce'
    import ordersApi from '../../api/orders'
    import SelectInput from '../SelectInput'
    import LineItemOptionsInput from './LineItemOptionsInput'

    export default {
        components: {
            SelectInput,
            LineItemOptionsInput
        },

        data() {
            return {
                showForm: false,
                selectedPurchasable: null,
                validLineItemOptions: false,
                lineItemOptionsErrors: []
            }
        },

        computed: {
            ...mapState({
                purchasables: state => state.purchasables,
            }),

            ...mapGetters([
                'getErrors',
                'canAddLineItem',
                'orderId',
            ]),

            formDisabled() {
                return !this.canAddLineItem
            },

            validPurchasable() {
                if (!this.canAddLineItem || !this.selectedPurchasable) {
                    return false
                }

                if(this.selectedPurchasable.isAvailable == false)
                {
                    return false;
                }

                return true;
            },

            submitDisabled() {
                if (!this.validPurchasable) {
                    return true
                }

                if (!this.lineItemOptionsConfig || !this.validLineItemOptions) {
                    return true
                }

                return false
            },

            lineItems() {
                return this.$store.state.draft.order.lineItems
            },

            lineItemOptionsConfig() {
                if (!this.validPurchasable) {
                    return {}
                }

                const lineItemOptionsConfig = this.$store.getters.lineItemOptionsConfig;

                if (lineItemOptionsConfig.hasOwnProperty(this.selectedPurchasable.type)) {
                    return lineItemOptionsConfig[this.selectedPurchasable.type];
                }

                return {}
            }
        },

        watch: {
            // When the selectedPurchasable changes, reset the line item options
            // validation and internal values
            selectedPurchasable: {
                handler() {
                    this.validLineItemOptions = false

                    this.$nextTick(() => {
                        if (this.$refs.lineItemOptions) {
                            this.$refs.lineItemOptions.setValues()
                        }
                    })
                }
            }
        },

        methods: {
            ...mapActions([
                'displayError',
            ]),

            handleShowForm() {
                this.showForm = true
            },

            handleHideForm() {
                this.showForm = false
                this.selectedPurchasable = null
            },

            onLineItemOptionsValidated(event) {
                this.validLineItemOptions = event.valid

                if (!event.valid) {
                    this.lineItemOptionsErrors = [event.error]
                } else {
                    this.lineItemOptionsErrors = []
                }
            },

            lineItemAdd() {
                if (!this.canAddLineItem) {
                    this.displayError(this.$options.filters.t("You are not allowed to add a line item.", 'commerce'));
                    return
                }

                if (this.lineItemOptionsConfig) {
                    if (!this.$refs.lineItemOptions.values) {
                        return
                    }

                    this.addLineItem(this.$refs.lineItemOptions.values)
                    return
                }

                this.addLineItem()
            },

            addLineItem(options) {

                if (options) {
                    options = JSON.parse(JSON.stringify(options))
                } else {
                    options = []
                }

                const lineItem = {
                    id: null,
                    lineItemStatusId: null,
                    salePrice: this.selectedPurchasable.price,
                    qty: "1",
                    note: "",
                    privateNote: "",
                    orderId: this.orderId,
                    purchasableId: this.selectedPurchasable.id,
                    sku: this.selectedPurchasable.sku,
                    options: options,
                    adjustments: [],
                }

                this.$emit('addLineItem', lineItem)
                this.handleHideForm()
            },

            onSearch({searchText, loading}) {
                loading(true);
                this.search(loading, searchText, this);
            },

            search: debounce((loading, searchText, vm) => {
                ordersApi.purchasableSearch(vm.orderId, escape(searchText))
                    .then((response) => {
                        vm.$store.commit('updatePurchasables', response.data)
                        loading(false)
                    })
            }, 350)
        },
    }
</script>

<style lang="scss">
    @import "~craftcms-sass/src/mixins";

    /* Starter */

    .starter {
        text-align: center;

        div[data-icon] {
            font-size: 4em;
            color: #f1f5f8;
        }
    }


    /* Add line item form */

    .add-line-item-form {
        max-width: 100%;
    }


    /* Purchasable select option */

    .purchasable-select-option {
        display: flex;

        .description {
            flex-grow: 1;
        }

        .sku {
            color: $lightTextColor;
            @include margin-right(20px);
        }

        &.notAvailable{
            .description {
                color: $lightTextColor;
            }
            .price {
                color: red;
            }
        }

        .price {
            width: 10%;
            text-align: right;
        }
    }

    /* Line item options */

    .line-item-options {
        margin-top: 20px;
    }
</style>

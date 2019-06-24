<template>
    <div>
        <template v-if="!showForm">
            <template v-if="lineItems.length > 0">
                <a @click.prevent="showForm = true">{{"Add a line item"|t('commerce')}}</a>
            </template>
            <template>
                <div class="starter">
                    <div data-icon="info"></div>
                    <h2>{{"Your order is empty"|t('commerce')}}</h2>
                    <a @click.prevent="showForm = true">{{"Create your first line item"|t('commerce')}}</a>
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
                                    <template v-if="slotProps.option.description.length<20">{{slotProps.option.description}}</template>
                                    <template v-if="slotProps.option.description.length>=20">{{slotProps.option.description.substring(0,20)+"…" }}</template>
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

                <div class="buttons">
                    <input type="button" class="btn" :class="{disabled: formDisabled}" :disabled="formDisabled" :value="$options.filters.t('Cancel', 'commerce')" @click="showForm = false" />
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

    export default {
        components: {
            SelectInput,
        },

        data() {
            return {
                showForm: false,
                selectedPurchasable: null,
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

            submitDisabled() {
                if (!this.canAddLineItem || !this.selectedPurchasable) {
                    return true
                }

                if(this.selectedPurchasable.isAvailable == false)
                {
                    return true;
                }

                return false
            },

            lineItems() {
                return this.$store.state.draft.order.lineItems
            }
        },

        methods: {
            ...mapActions([
                'displayError',
            ]),

            lineItemAdd() {
                if (!this.canAddLineItem) {
                    this.displayError(this.$options.filters.t("You are not allowed to add a line item.", 'commerce'));
                    return
                }

                this.addLineItem(this.selectedPurchasable)
            },

            addLineItem() {
                const lineItem = {
                    id: null,
                    lineItemStatusId: null,
                    salePrice: this.selectedPurchasable.price,
                    qty: "1",
                    note: "",
                    adminNote: "",
                    orderId: this.orderId,
                    purchasableId: this.selectedPurchasable.id,
                    sku: this.selectedPurchasable.sku,
                    options: {giftWrapped: "no"},
                    adjustments: [],
                }

                this.$emit('addLineItem', lineItem)
                this.selectedPurchasable = null
                this.showForm = false
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
    .add-line-item-form {
        max-width: 500px;
    }

    .purchasable-select-option {
        display: flex;

        .description {
            flex-grow: 1;
        }

        .sku {
            color: #888;
            margin-right: 20px;
        }

        &.notAvailable{
            color: red;
        }

        .price {
            width: 10%;
            text-align: right;
        }
    }
</style>
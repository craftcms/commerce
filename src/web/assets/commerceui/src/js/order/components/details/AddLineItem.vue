<template>
    <div>
        <template v-if="!showForm">
            <template v-if="lineItems.length > 0">
                <div class="text-right">
                    <btn-link @click="showForm = true" button-class="btn submit">{{"Add a line item"|t('commerce')}}</btn-link>
                </div>
            </template>
            <template v-else>
                <div class="starter">
                    <div data-icon="info"></div>
                    <h2>{{"Your order is empty"|t('commerce')}}</h2>
                    <btn-link @click="showForm = true">{{"Create your first line item"|t('commerce')}}</btn-link>
                </div>
            </template>
        </template>
        <template v-else>
            <div>
                <div class="flex add-line-item-table-header">
                    <h2>{{ $options.filters.t('Add a line item', 'commerce') }}</h2>
                    <form @submit.prevent="lineItemAdd()" class="add-line-item-form">
                        <div class="buttons buttons--add-line-item">
                            <input type="button" class="btn" :class="{disabled: formDisabled}" :disabled="formDisabled"
                                   :value="$options.filters.t('Cancel', 'commerce')" @click="showForm = false"/>
                            <input type="submit" class="btn submit" :class="{disabled: submitDisabled}"
                                   :disabled="submitDisabled" :value="$options.filters.t('Add', 'commerce')" @click.prevent="lineItemAdd()"/>
                        </div>
                    </form>
                </div>
                <admin-table
                        :allow-multiple-selections="true"
                        table-data-endpoint="commerce/orders/purchasables-table"
                        :checkboxes="true"
                        :columns="purchasableTableColumns"
                        :padded="true"
                        per-page="10"
                        search="true"
                        @onSelect="handleCheckboxSelect"
                        @data="handleTableData"
                ></admin-table>

                <form @submit.prevent="lineItemAdd()" class="add-line-item-form">
                    <div class="buttons buttons--add-line-item">
                        <input type="button" class="btn" :class="{disabled: formDisabled}" :disabled="formDisabled"
                               :value="$options.filters.t('Cancel', 'commerce')" @click="showForm = false"/>
                        <input type="submit" class="btn submit" :class="{disabled: submitDisabled}"
                               :disabled="submitDisabled" :value="$options.filters.t('Add', 'commerce')" @click.prevent="lineItemAdd()"/>
                    </div>
                </form>

            </div>

        </template>
    </div>
</template>

<script>
    import {mapActions, mapGetters, mapState} from 'vuex'
    import debounce from 'lodash.debounce'
    import _find from 'lodash.find'
    import ordersApi from '../../api/orders'
    import AdminTable from 'Craft/admintable/src/App'

    export default {
        components: {
            AdminTable
        },

        data() {
            return {
                showForm: false,
                selectedPurchasables: [],
                currentTableData: null,
                purchasableTableColumns: [
                    { name: 'description', title: this.$options.filters.t('Description', 'commerce') },
                    { name: 'sku', title: this.$options.filters.t('SKU', 'commerce') },
                    { name: 'priceAsCurrency', title: this.$options.filters.t('Price', 'commerce') },
                    { name: 'isAvailable', title: this.$options.filters.t('Available?', 'commerce'), callback: function(value) {
                        if (value) {
                            return '<span data-icon="check" title=""></span>'
                        }
                    } }
                ],
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
                if (!this.canAddLineItem || !this.selectedPurchasables.length) {
                    return true
                }

                return false;
            },

            lineItems() {
                return this.$store.state.draft.order.lineItems;
            }
        },

        methods: {
            ...mapActions([
                'displayError',
            ]),

            lineItemAdd() {
                if (!this.canAddLineItem) {
                    this.displayError(this.$options.filters.t("You are not allowed to add a line item.", 'commerce'));
                    return;
                }

                this.addLineItem();
            },

            addLineItem() {
                if (this.selectedPurchasables.length) {
                    let lineItems = [];
                    for (let i = 0; i < this.selectedPurchasables.length; i++) {
                        let purchasable = this.selectedPurchasables[i];
                        if (purchasable && purchasable.isAvailable) {
                            lineItems.push({
                                id: null,
                                lineItemStatusId: null,
                                salePrice: purchasable.price,
                                qty: '1',
                                note: '',
                                privateNote: '',
                                orderId: this.orderId,
                                purchasableId: purchasable.id,
                                sku: purchasable.sku,
                                options: [],
                                adjustments: [],
                            });
                        }
                    }

                    if (lineItems.length) {
                        this.$emit('addLineItem', lineItems);
                    }
                }

                this.selectedPurchasables = [];
                this.showForm = false;
            },

            handleCheckboxSelect(ids) {
                if (ids && ids.length) {
                    let $this = this;
                    this.selectedPurchasables = ids.map(id => {
                        return _find($this.currentTableData, { id: id });
                    });
                } else {
                    this.selectedPurchasables = [];
                }
            },

            handleTableData(data) {
                this.currentTableData = data;
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
    @import "../../../../../node_modules/craftcms-sass/src/mixins";

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

    .add-line-item-table-header {
        justify-content: space-between;
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

    .buttons--add-line-item {
        justify-content: flex-end;
    }
</style>

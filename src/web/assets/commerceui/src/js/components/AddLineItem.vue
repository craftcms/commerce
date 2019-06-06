<template>
    <div>
        <template v-if="!showForm">
            <a @click.prevent="showForm = true">Add a line item</a>
        </template>
        <template v-else>
            <form @submit.prevent="lineItemAdd()" class="add-line-item-form">
                <v-select
                        label="sku"
                        v-model="selectedPurchasable"
                        :options="purchasables"
                        :disabled="formDisabled"
                        :filterable="false"
                        @search="onSearch">
                    <template slot="option" slot-scope="option">
                        <div class="purchasable-select-option" v-bind:class="{ notAvailable: !option.isAvailable }">
                            <div class="description">
                                <template v-if="option.description">
                                    <template v-if="option.description.length<20">{{option.description}}</template>
                                    <template v-if="option.description.length>=20">{{option.description.substring(0,20)+".." }}</template>
                                    <template v-if="!option.isAvailable"> (Not available)</template>
                                </template>
                                <template v-else>
                                    <em>No description</em>
                                </template>
                            </div>
                            <div class="sku">{{ option.sku }}</div>
                            <div class="price">{{ option.priceAsCurrency }}</div>
                        </div>
                    </template>
                </v-select>

                <div class="buttons">
                    <input type="button" class="btn" :class="{disabled: formDisabled}" :disabled="formDisabled" value="Cancel" @click="showForm = false" />
                    <input type="submit" class="btn submit" :class="{disabled: submitDisabled}" :disabled="submitDisabled" value="Add" />
                </div>

                <div v-if="recalculateLoading" class="spinner"></div>
            </form>
        </template>
    </div>
</template>

<script>
    import {mapState, mapGetters, mapActions} from 'vuex'
    import {debounce} from 'debounce'
    import VSelect from 'vue-select'
    import purchasablesApi from '../api/purchasables'

    export default {
        components: {
            VSelect,
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
                recalculateLoading: state => state.recalculateLoading,
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
            }
        },

        methods: {
            ...mapActions([
                'displayError',
            ]),

            lineItemAdd() {
                if (!this.canAddLineItem) {
                    this.displayError('You are not allowed to add a line item.');
                    return
                }

                this.addLineItem(this.selectedPurchasable)
            },

            addLineItem() {
                const lineItem = {
                    id: null,
                    lineItemStatusId: null,
                    salePrice: '0.0000',
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
            },

            onSearch(search, loading) {
                loading(true);
                this.search(loading, search, this);
            },

            search: debounce((loading, search, vm) => {
                purchasablesApi.search(vm.orderId, escape(search))
                    .then((response) => {
                        vm.purchasables = JSON.parse(JSON.stringify(response.data))
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
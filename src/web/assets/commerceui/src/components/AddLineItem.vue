<template>
    <form @submit.prevent="lineItemAdd()">
        <v-select label="sku" v-model="selectedPurchasable" :options="$root.purchasables">
            <template slot="option" slot-scope="option">
                <div class="purchasable-select-option">
                    <div class="description">
                        <template v-if="option.description">
                            {{option.description}}
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

        <br />

        <input type="submit" class="btn submit" value="Add Line Item" />

        <div v-if="loading" class="spinner"></div>
    </form>
</template>

<script>
    import axios from 'axios'
    import VSelect from 'vue-select'

    export default {
        components: {
            VSelect,
        },
        props: {
            draft: {
                type: Object,
            },
            loading: {
                type: Boolean,
            },
            orderId: {
                type: Number,
            }
        },

        data() {
            return {
                selectedPurchasable: null,
                vselectSelected: null,
            }
        },

        methods: {
            lineItemAdd() {
                const lineItem = {
                    id: null,
                    lineItemStatusId: null,
                    salePrice: '0.0000',
                    qty: "1",
                    note: "",
                    orderId: this.orderId,
                    purchasableId: this.selectedPurchasable.id,
                    sku: this.selectedPurchasable.sku,
                    options: {giftWrapped: "no"},
                    adjustments: [],
                }

                this.draft.order.lineItems.push(lineItem)

                this.$emit('change')
            },
        },
    }
</script>

<style lang="scss">
    .purchasable-select-option {
        display: flex;

        .description {
            flex-grow: 1;
        }
        
        .sku {
            color: #888;
            margin-right: 20px;
        }

        .price {
            width: 10%;
            text-align: right;
        }
    }
</style>
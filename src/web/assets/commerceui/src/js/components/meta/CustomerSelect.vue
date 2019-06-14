<template>
    <div class="v-select-btn">
        <v-select
                ref="vSelect"
                label="email"
                v-model="selectedCustomer"
                :options="customers"
                :filterable="false"
                :create-option="createOption"
                taggable
                @search="onSearch">
            <template slot="option" slot-scope="option">
                <div class="customer-select-option">
                    <template v-if="!option.customerId">
                        Create “{{option.email}}”
                    </template>
                    <template v-else>
                        {{option.email}}
                    </template>
                </div>
            </template>
        </v-select>
    </div>
</template>

<script>
    import {mapState} from 'vuex'
    import debounce from 'lodash.debounce'
    import VSelect from 'vue-select'

    export default {
        components: {
            VSelect,
        },

        props: {
            order: {
                type: Object,
            },
        },

        data() {
            return {
                selectedCustomer: null,
            }
        },

        computed: {
            ...mapState({
                customers: state => state.customers,
            }),


            customer() {
                if (this.customerId !== 0) {
                    for (let customersKey in this.customers) {
                        const customer = this.customers[customersKey]

                        if (customer.id === this.customerId) {
                            return customer
                        }
                    }
                }

                return {id: 0, name: "None"}
            },

            customerId: {
                get() {
                    return this.order.customerId
                },
                set(value) {
                    const order = this.order
                    order.customerId = value
                    this.$emit('updateOrder', order)
                }
            }
        },

        methods: {
            createOption(searchText) {
                return {customerId: null, email: searchText, totalOrders: 0, userId: null, firstName: null, lastName: null}
            },

            onSearch(search, loading) {
                loading(true);
                this.search(loading, search, this);
            },

            search: debounce((loading, search, vm) => {
                vm.$store.dispatch('customerSearch', search)
                    .then(() => {
                        loading(false)
                    })
            }, 350),
        },

        mounted() {
            const customer = {customerId: this.customerId, email: this.order.email}
            this.$store.commit('updateCustomers', [customer])
            this.selectedCustomer = customer
        }
    }
</script>
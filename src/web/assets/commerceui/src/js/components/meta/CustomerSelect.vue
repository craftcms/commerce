<template>
    <select-input
            ref="vSelect"
            label="email"
            v-model="selectedCustomer"
            :options="customers"
            :filterable="false"
            :clearable="false"
            :create-option="createOption"
            placeholder="Search customer…"
            taggable
            @search="onSearch">
        <template v-slot:option="slotProps">
            <div class="customer-select-option">
                <template v-if="!slotProps.option.customerId">
                    Create “{{slotProps.option.email}}”
                </template>
                <template v-else>
                    {{slotProps.option.email}}
                </template>
            </div>
        </template>
    </select-input>
</template>

<script>
    import {mapState} from 'vuex'
    import debounce from 'lodash.debounce'
    import SelectInput from '../SelectInput'

    export default {
        components: {
            SelectInput,
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
            },
        },

        methods: {
            createOption(searchText) {
                const order = JSON.parse(JSON.stringify(this.order))
                order.customerId = null
                order.email = searchText
                this.$emit('updateOrder', order)

                return {customerId: null, email: searchText, totalOrders: 0, userId: null, firstName: null, lastName: null}
            },

            onSearch({searchText, loading}) {
                loading(true);
                this.search(loading, searchText, this);
            },

            search: debounce((loading, search, vm) => {
                vm.$store.dispatch('customerSearch', search)
                    .then(() => {
                        loading(false)
                    })
            }, 350),
        },

        mounted() {
            if (this.order.email) {
                const customer = {customerId: this.customerId, email: this.order.email}
                this.$store.commit('updateCustomers', [customer])
                this.selectedCustomer = customer
            }
        }
    }
</script>
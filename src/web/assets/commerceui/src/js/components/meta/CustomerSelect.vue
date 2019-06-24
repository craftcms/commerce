<template>
    <select-input
            ref="vSelect"
            label="email"
            v-model="selectedCustomer"
            :options="customers"
            :filterable="false"
            :clearable="false"
            :create-option="createOption"
            :placeholder="$options.filters.t('Search customer…', 'commerce')"
            taggable
            @search="onSearch">
        <template v-slot:option="slotProps">
            <div class="customer-select-option">
                <template v-if="!slotProps.option.customerId">
                    {{"Create “{email}”"|t('commerce', {email: slotProps.option.email})}}
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
    import { validationMixin } from 'vuelidate'
    import { required, email } from 'vuelidate/lib/validators'

    export default {
        mixins: [validationMixin],

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
                newCustomerEmail: null,
            }
        },

        validations: {
            newCustomerEmail: {
                required,
                email
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

                return {id: 0, name: this.$options.filters.t("None", 'commerce')}
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
                if (this.$v.newCustomerEmail.$invalid) {
                    this.$store.dispatch('displayError', this.$options.filters.t("Invalid email.", 'commerce'))

                    this.$nextTick(() => {
                        this.$refs.vSelect.$children[0].search = searchText
                    })

                    return {customerId: this.customerId, email: this.order.email}
                }

                const order = JSON.parse(JSON.stringify(this.order))
                order.customerId = null
                order.email = searchText
                this.$emit('updateOrder', order)

                return {customerId: null, email: searchText, totalOrders: 0, userId: null, firstName: null, lastName: null}
            },

            onSearch({searchText, loading}) {
                loading(true);
                this.search(loading, searchText, this);

                this.newCustomerEmail = searchText
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
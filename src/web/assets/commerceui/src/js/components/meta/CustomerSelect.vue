<template>
    <select-input
            ref="vSelect"
            label="email"
            v-model="selectedCustomer"
            :options="customers"
            :filterable="false"
            :clearable="false"
            :create-option="createOption"
            :placeholder="$options.filters.t('Search…', 'commerce')"
            taggable
            @input="onChange"
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
    import {validationMixin} from 'vuelidate'
    import {email, required} from 'vuelidate/lib/validators'

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

            customerId() {
                return this.order.customerId
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

            onChange() {
                const order = JSON.parse(JSON.stringify(this.order))
                order.customerId = this.selectedCustomer.customerId
                order.email = this.selectedCustomer.email
                this.$emit('updateOrder', order)
            }
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

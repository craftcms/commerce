<template>
    <select-input
            ref="vSelect"
            label="email"
            class="customer-select"
            v-model="selectedCustomer"
            :options="customers"
            :filterable="false"
            :clearable="false"
            :pre-filtered="true"
            :create-option="createOption"
            :placeholder="$options.filters.t('Search…', 'commerce')"
            :clear-search-on-blur="false"
            taggable
            @input="onChange"
            @search="onSearch">
        <template v-slot:option="slotProps">
            <div class="customer-select-option">
                <template v-if="!slotProps.option.id">
                    <div class="order-flex align-center">

                        <div class="customer-photo-wrapper">
                            <div class="customer-photo order-flex customer-photo--initial justify-center align-center">
                                <img class="w-full" :src="userPhotoFallback()" :alt="$options.filters.t('New Customer', 'commerce')">
                            </div>
                        </div>
                        <div class="ml-1">
                            {{"Create “{email}”"|t('commerce', {email: slotProps.option.email})}}
                        </div>
                    </div>
                </template>
                <template v-else>
                    <div class="customer-select-option">
                        <customer
                            :customer="{
                                photo: slotProps.option.photo,
                                user: slotProps.option.user,
                                email: slotProps.option.email,
                                fullName: slotProps.option.billingFullName,
                                firstName: slotProps.option.billingFirstName,
                                lastName: slotProps.option.billingLastName,
                            }"
                        ></customer>
                    </div>
                </template>
            </div>
        </template>
    </select-input>
</template>

<script>
    import {mapGetters, mapState} from 'vuex'
    import debounce from 'lodash.debounce'
    import SelectInput from '../../../base/components/SelectInput'
    import {validationMixin} from 'vuelidate'
    import {email, required} from 'vuelidate/lib/validators'
    import Customer from '../customer/Customer';

    export default {
        mixins: [validationMixin],

        components: {
            Customer,
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
            ...mapGetters([
                'userPhotoFallback'
            ]),

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
                this.$emit('update', this.selectedCustomer);
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

<style lang="scss">
    @import '../../../../sass/order/app';

    .customer-select {
        .vs__dropdown-option {
            border-top: 1px solid $lightGrey;
            padding: 6px 14px;
        }

        .vs__dropdown-menu {
            border-radius: $paneBorderRadius;
            padding: 0;

            li:first-child {
                border-top: none;
            }
        }

        .vs__dropdown-option--highlight {
            background-color: $bgColor;
        }
    }
</style>

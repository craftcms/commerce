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
        :placeholder="
            $options.filters.t('Search or enter customer email…', 'commerce')
        "
        :clear-search-on-blur="false"
        taggable
        @input="onChange"
        @search="onSearch"
        @created="onCreated"
    >
        <template v-slot:option="slotProps">
            <div
                class="customer-select-option"
                v-if="
                    slotProps.option.id ||
                    (!slotProps.option.id && !customers.length)
                "
            >
                <template
                    v-if="
                        !slotProps.option.id &&
                        !customers.length &&
                        $v.newCustomerEmail.$invalid
                    "
                >
                    <div class="order-flex justify-center">
                        <div>
                            {{
                                $options.filters.t(
                                    'A valid email is required to create a customer.',
                                    'commerce'
                                )
                            }}
                        </div>
                    </div>
                </template>
                <template
                    v-else-if="
                        !slotProps.option.id &&
                        !$v.newCustomerEmail.$invalid &&
                        !customers.length
                    "
                >
                    <div class="order-flex align-center">
                        <div class="customer-photo-wrapper">
                            <div
                                class="customer-photo order-flex customer-photo--initial customer-photo--email justify-center align-center"
                            >
                                <span class="icon" data-icon="plus"></span>
                            </div>
                        </div>
                        <div class="ml-1">
                            {{
                                'Create customer: “{email}”'
                                    | t('commerce', {
                                        email: slotProps.option.email,
                                    })
                            }}
                        </div>
                    </div>
                </template>
                <template v-else-if="slotProps.option.id">
                    <div>
                        <customer :customer="slotProps.option"></customer>
                    </div>
                </template>
            </div>
        </template>
    </select-input>
</template>

<script>
    import {mapGetters, mapState} from 'vuex';
    import axios from 'axios/index';
    import debounce from 'lodash.debounce';
    import SelectInput from '../../../base/components/SelectInput';
    import {validationMixin} from 'vuelidate';
    import {email, required} from 'vuelidate/lib/validators';
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
                customerSearchRequest: null,
                selectedCustomer: null,
                newCustomerEmail: null,
            };
        },

        validations: {
            newCustomerEmail: {
                required,
                email,
            },
        },

        computed: {
            ...mapState({
                customers: (state) => state.customers,
            }),

            customerId() {
                return this.order.customerId;
            },
        },

        methods: {
            ...mapGetters(['userPhotoFallback']),

            createOption(searchText) {
                const data = {id: null, email: searchText};

                return data;
            },

            onCreated({id, email}) {
                if (this.$v.newCustomerEmail.$invalid) {
                    this.$store.dispatch(
                        'displayError',
                        this.$options.filters.t('Invalid email.', 'commerce')
                    );

                    this.$nextTick(() => {
                        this.$refs.vSelect.$children[0].search = email;
                    });
                    return;
                }

                const data = {email};
                Craft.sendActionRequest(
                    'POST',
                    'commerce/orders/create-customer',
                    {data}
                )
                    .then((response) => {
                        this.selectedCustomer = response.data.user;

                        this.onChange();
                        return this.selectedCustomer;
                    })
                    .catch((error) => {
                        this.$store.dispatch(
                            'displayError',
                            error.response.data.message
                        );
                        this.$nextTick(() => {
                            this.$refs.vSelect.$children[0].search = searchText;
                        });

                        return {
                            id: this.customerId,
                            email: this.order.customer
                                ? this.order.customer.email
                                : null,
                        };
                    });
            },

            onSearch({searchText, loading}) {
                loading(true);
                this.search(loading, searchText, this);

                this.newCustomerEmail = searchText;
            },

            search: debounce((loading, search, vm) => {
                if (vm.customerSearchRequest) {
                    vm.customerSearchRequest.cancel();
                }

                vm.customerSearchRequest = axios.CancelToken.source();

                vm.$store
                    .dispatch('customerSearch', {
                        query: search,
                        cancelToken: vm.customerSearchRequest.token,
                    })
                    .then(() => {
                        loading(false);
                        vm.customerSearchRequest = null;
                    })
                    .catch((err) => {
                        if (!axios.isCancel(err)) {
                            vm.$store.dispatch('displayError', err);
                        }
                    });
            }, 500),

            onChange() {
                if (this.selectedCustomer && this.selectedCustomer.id) {
                    this.$emit('update', this.selectedCustomer);
                }
            },
        },

        mounted() {
            if (this.order.customerId) {
                let customer = {customerId: this.order.customer.id};

                this.$store.commit('updateCustomers', [customer]);
                this.selectedCustomer = customer;
            }
        },
    };
</script>

<style lang="scss">
    @import '../../../../sass/order/app';

    .customer-select-option {
        border-top: 1px solid $lightGrey;
        padding: 6px 14px;
    }

    .customer-select {
        .vs__dropdown-option {
            padding: 0;
        }

        .vs__dropdown-menu {
            border-radius: $largeBorderRadius;
            padding: 0;

            li:first-child {
                border-top: none;
            }
        }

        .vs__dropdown-option--highlight {
            background-color: $bgColor;
        }
    }

    .customer-select--error {
        border: 1px solid var(--error-color);
        border-radius: 5px;
    }
</style>

<template>
    <select-input
            ref="vSelect"
            label="email"
            v-model="selectedCustomer"
            :options="customers"
            :filterable="false"
            :clearable="false"
            :pre-filtered="true"
            :create-option="createOption"
            :placeholder="$options.filters.t('Search…', 'commerce')"
            taggable
            @input="onChange"
            @search="onSearch">
        <template v-slot:option="slotProps">
            <div class="customer-select-option">
                <template v-if="!slotProps.option.id">
                    {{"Create “{email}”"|t('commerce', {email: slotProps.option.email})}}
                </template>
                <template v-else>
                    <div class="customer-select-option">
                        <div class="order-flex align-center">
                            <div
                                class="customer-photo order-flex justify-center align-center"
                                :class="{ 'customer-photo--initial': !slotProps.option.photo }"
                            >
                                <img v-if="slotProps.option.photo" class="w-full" :src="slotProps.option.photo" :alt="slotProps.option.email">
                                <div v-if="!slotProps.option.photo && slotProps.option.billingFullName">{{slotProps.option.billingFullName[0]}}</div>
                                <div v-if="!slotProps.option.photo && !slotProps.option.billingFullName && slotProps.option.billingFirstName">{{slotProps.option.billingFirstName[0]}}</div>
                            </div>
                            <div class="ml-1">
                                <div class="order-flex align-center" v-if="slotProps.option.billingFullName || slotProps.option.billingFirstName || slotProps.option.billingLastName || slotProps.option.user">
                                    <div v-if="slotProps.option.billingFullName">{{slotProps.option.billingFullName}}</div>
                                    <div v-if="!slotProps.option.billingFullName && (slotProps.option.billingFirstName || slotProps.option.billingLastName)">
                                        {{slotProps.option.billingFirstName}}<span v-if="slotProps.option.billingFirstName && slotProps.option.billingLastName">&nbsp;</span>{{slotProps.option.billingLastName}}
                                    </div>
                                    <div v-if="slotProps.option.user" class="ml-2 customer-select-option-user">
                                        <span class="status" :class="slotProps.option.user.status"></span>
                                        <span class="cell-bold">{{slotProps.option.user.title}}</span>
                                    </div>
                                </div>
                                <div class="order-flex">
                                    <div class="light">{{slotProps.option.email}}</div>
                                    <div class="ml-1" v-if="slotProps.option.user"></div>
                                </div>
                            </div>
                        </div>
                    </div>
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
    @import '../../../sass/app';
    .customer-photo {
        width: 30px;
        height: 30px;
        overflow: hidden;
        border-radius: 50%;

        &--initial {
            background-color: $lightGrey;
            color: $grey;
        }
    }

    .customer-select-option {
        &-user {
            color: $black;
            font-weight: bold;
            font-size: .875em;
        }
    }

    .customer-select-option .status {
        body.ltr & {
            margin-right: 4px;
        }

        body.rtl & {
            margin-left: 4px;
        }

    }

</style>

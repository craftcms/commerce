<template>
    <div class="v-select-btn">
        <v-select
                ref="vSelect"
                label="email"
                v-model="selectedCustomer"
                :options="customers"
                :filterable="false"
                :clearable="false"
                :create-option="createOption"
                searchInputQuerySelector="[type=text]"
                taggable
                @search="onSearch" :components="{OpenIndicator}">
            <template slot="selected-option" slot-scope="option">
                <div @click="onOptionClick">
                    {{option.email}}
                </div>
            </template>

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

            <template slot="spinner" slot-scope="spinner">
                <div class="spinner-wrapper" v-if="spinner.loading">
                    <div class="spinner"></div>
                </div>
            </template>

            <template slot="search" slot-scope="{attributes, events}">
                <input class="vs__search" type="text" v-bind="attributes" v-on="events">
            </template>
        </v-select>
    </div>
</template>

<script>
    import {mapState} from 'vuex'
    import debounce from 'lodash.debounce'
    import VSelect from 'vue-select'
    import OpenIndicator from './OpenIndicator'

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
                OpenIndicator
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

            onOptionClick() {
                // Todo: Get rid of workaround once this issue is fixed
                // https://github.com/sagalbot/vue-select/issues/882
                if (!this.$refs.vSelect.open) {
                    this.$refs.vSelect.open = true;
                    this.$refs.vSelect.searchEl.focus();
                }
            }
        },

        mounted() {
            const customer = {customerId: this.customerId, email: this.order.email}
            this.$store.commit('updateCustomers', [customer])
            this.selectedCustomer = customer
        }
    }
</script>
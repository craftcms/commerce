<template>
    <div>
        <a class="btn menubtn" ref="customer">
            {{customer.email}}
        </a>

        <div class="menu">
            <ul class="padded" role="listbox">
                <li v-for="(customer) in customers">
                    <a
                            :data-id="customer.id"
                            :data-name="customer.email"
                            :class="{sel: customer.id === customer.value}">
                        {{customer.email}}
                    </a>
                </li>
            </ul>
        </div>
    </div>
</template>

<script>
    /* global Garnish */

    import {mapGetters} from 'vuex'

    export default {
        props: {
            order: {
                type: Object,
            },
        },

        computed: {
            ...mapGetters([
                'customers',
            ]),

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
            onSelectCustomer(customer) {
                this.customer = customer.dataset.id
            },
        },

        mounted() {
            new Garnish.MenuBtn(this.$refs.customer, {
                onOptionSelect: this.onSelectCustomer
            })
        }
    }
</script>
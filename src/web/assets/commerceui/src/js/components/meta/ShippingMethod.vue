<template>
    <div>
        <v-select
                label="name"
                v-model="selectedShippingMethod"
                :options="shippingMethods"
                :filterable="false"
                @input="onChange"
                @search="onSearch">
            <template slot="option" slot-scope="option">
                <div class="shipping-method-select-option">
                    {{option.name}}
                </div>
            </template>
        </v-select>
    </div>
</template>

<script>
    /* global Garnish */
    import {mapState, mapGetters} from 'vuex'
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
                selectedShippingMethod: null,
            }
        },

        computed: {
            ...mapGetters([
                'shippingMethods',
            ]),

            shippingMethod() {
                if (this.shippingMethodHandle !== 0) {
                    for (let shippingMethodsKey in this.shippingMethods) {
                        const shippingMethod = this.shippingMethods[shippingMethodsKey]

                        if (shippingMethod.handle === this.shippingMethodHandle) {
                            return shippingMethod
                        }
                    }
                }

                return {id: 0, name: "None", color: null}
            },

            shippingMethodHandle: {
                get() {
                    return this.order.shippingMethodHandle
                },
                set(value) {
                    const order = JSON.parse(JSON.stringify(this.order))
                    order.shippingMethodHandle = value
                    this.$emit('updateOrder', order)
                }
            }
        },

        methods: {
            onChange() {
                this.shippingMethodHandle = this.selectedShippingMethod.handle
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
            }, 350)
        },

        mounted() {
            const shippingMethod = this.shippingMethods.find(s => s.handle === this.order.shippingMethodHandle)
            this.$store.commit('updateCustomers', [shippingMethod])
            this.selectedShippingMethod = shippingMethod
        }
    }
</script>
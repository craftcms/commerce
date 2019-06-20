<template>
    <div>
        <select-input
                label="name"
                :options="shippingMethods"
                :filterable="false"
                v-model="selectedShippingMethod"
                @input="onChange"
                @search="onSearch"
        >
            <template v-slot:option="slotProps">
                <div class="shipping-method-select-option">
                    {{slotProps.option.name}}
                </div>
            </template>
        </select-input>
    </div>
</template>

<script>
    /* global Garnish */
    import {mapGetters} from 'vuex'
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

            onSearch({searchText, loading}) {
                loading(true);
                this.search(loading, searchText, this);
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
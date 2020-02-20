<template>
    <div>
        <select-input
                label="name"
                :options="shippingMethods"
                :filterable="true"
                v-model="selectedShippingMethod"
                :placeholder="shippingMethodHandle"
                @input="onChange"
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
            shippingMethods() {
                return [{handle: 'none', name: this.$options.filters.t("None", 'commerce')}, ...this.$store.getters.shippingMethods]
            },

            shippingMethod() {
                if (this.shippingMethodHandle !== 0) {
                    for (let shippingMethodsKey in this.shippingMethods) {
                        const shippingMethod = this.shippingMethods[shippingMethodsKey]

                        if (shippingMethod.handle === this.shippingMethodHandle) {
                            return shippingMethod
                        }
                    }
                }

                return {handle: 'none', name: this.$options.filters.t("None", 'commerce')}
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
                if (this.selectedShippingMethod.handle === 'none') {
                    this.shippingMethodHandle = null
                } else {
                    this.shippingMethodHandle = this.selectedShippingMethod.handle
                }
            },
        },

        mounted() {
            const shippingMethod = this.shippingMethods.find(s => s.handle === this.order.shippingMethodHandle)
            this.selectedShippingMethod = shippingMethod
        }
    }
</script>

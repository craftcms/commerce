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
                    <span class="status" :class="{ enabled: slotProps.option.matchesOrder, disabled: !slotProps.option.matchesOrder }"></span>{{slotProps.option.name}}
                </div>
            </template>
            <template v-slot:selected-option="slotProps">
                <div>
                    <span class="status" :class="{ enabled: slotProps.selectedOption.matchesOrder, disabled: !slotProps.selectedOption.matchesOrder }"></span>{{slotProps.selectedOption.name}}
                </div>
            </template>
        </select-input>
    </div>
</template>

<script>
    import SelectInput from '../../../base/components/SelectInput'

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
                return [this.noneShippingMethod, ...this.$store.getters.shippingMethods]
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

                return this.noneShippingMethod
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
            },

            noneShippingMethod() {
                return {handle: 'none', name: this.$options.filters.t("None", 'commerce'), matchesOrder: true};
            },

            orderShippingMethodHandle() {
                return this.order.shippingMethodHandle;
            }
        },

        watch: {
            orderShippingMethodHandle(val) {
                if (!val) {
                    this.selectedShippingMethod = null;
                }

                if (this.selectedShippingMethod && val != this.selectedShippingMethod.handle) {
                    this.selectedShippingMethod = this.shippingMethods.find(s => s.handle === val);
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

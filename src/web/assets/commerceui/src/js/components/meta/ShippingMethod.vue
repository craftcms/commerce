<template>
    <div>
        <a class="btn menubtn" ref="shippingMethod">
            {{shippingMethod.name}}
        </a>

        <div class="menu">
            <ul class="padded" role="listbox">
                <li v-for="(shippingMethod) in shippingMethods">
                    <a
                            :data-id="shippingMethod.id"
                            :data-name="shippingMethod.name"
                            :class="{sel: shippingMethod.id === shippingMethod.value}">
                        {{shippingMethod.name}}
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
                    const order = this.order
                    order.shippingMethodHandle = value
                    this.$emit('updateOrder', order)
                }
            }
        },

        methods: {
            onSelectShippingMethod(shippingMethod) {
                this.shippingMethodHandle = shippingMethod.dataset.handle
            },
        },

        mounted() {
            new Garnish.MenuBtn(this.$refs.shippingMethod, {
                onOptionSelect: this.onSelectShippingMethod
            })
        }
    }
</script>
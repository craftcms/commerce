<template>
    <div id="order-edit-shipping-method-wrapper">
        <div v-if="loading" class="spinner"></div>

        <template v-else-if="showSelect">
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
                        <span
                            class="status"
                            :class="{
                                enabled: slotProps.option.matchesOrder,
                                disabled: !slotProps.option.matchesOrder,
                            }"
                        ></span
                        >{{ slotProps.option.name }}
                    </div>
                </template>
                <template v-slot:selected-option="slotProps">
                    <div>
                        <span
                            class="status"
                            :class="{
                                enabled: slotProps.selectedOption.matchesOrder,
                                disabled:
                                    !slotProps.selectedOption.matchesOrder,
                            }"
                        ></span
                        >{{ slotProps.selectedOption.name }}
                    </div>
                </template>
            </select-input>
        </template>

        <template v-else>
            <div class="flex flex-nowrap align-center">
                <div class="flex-grow">
                    <span>{{ currentMethodName }}</span
                    ><br />
                    <span class="small code shipping-method-handle">{{
                        orderShippingMethodHandle
                    }}</span>
                </div>
                <button class="btn small icon" data-icon="edit" @click="onEdit">
                    {{ 'Edit' | t('commerce') }}
                </button>
            </div>
        </template>
    </div>
</template>

<script>
    import SelectInput from '../../../base/components/SelectInput';
    import ordersApi from '../../api/orders';
    import utils from '../../helpers/utils';

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
                loading: false,
                showSelect: false,
                selectedShippingMethod: null,
            };
        },

        computed: {
            shippingMethods() {
                return [
                    this.noneShippingMethod,
                    ...this.$store.getters.shippingMethods,
                ];
            },

            shippingMethodHandle() {
                return this.order.shippingMethodHandle;
            },

            currentMethodName() {
                return (
                    this.order.shippingMethodName ||
                    this.order.shippingMethodHandle ||
                    this.$options.filters.t('None', 'commerce')
                );
            },

            noneShippingMethod() {
                return {
                    handle: 'none',
                    name: this.$options.filters.t('None', 'commerce'),
                    matchesOrder: true,
                };
            },

            orderShippingMethodHandle() {
                return this.order.shippingMethodHandle || '';
            },
        },

        watch: {
            orderShippingMethodHandle() {
                this.showSelect = false;
                this.$store.commit('updateShippingMethodOptions', null);
                this.selectedShippingMethod = null;
            },
        },

        methods: {
            onEdit() {
                this.loading = true;
                const data = utils.buildDraftData(this.$store.state.draft);

                ordersApi
                    .getShippingMethodOptions(data)
                    .then((response) => {
                        this.loading = false;
                        this.$store.commit(
                            'updateShippingMethodOptions',
                            response.data.shippingMethodOptions
                        );
                        this.showSelect = true;
                        this.selectedShippingMethod =
                            this.shippingMethods.find(
                                (s) =>
                                    s.handle === this.order.shippingMethodHandle
                            ) || null;
                    })
                    .catch(() => {
                        this.loading = false;
                    });
            },

            setShippingMethod(handle, name) {
                const order = JSON.parse(JSON.stringify(this.order));
                order.shippingMethodHandle = handle;
                order.shippingMethodName = name;
                this.$emit('updateOrder', order);
            },

            onChange() {
                if (this.selectedShippingMethod.handle === 'none') {
                    this.setShippingMethod(null, null);
                } else {
                    this.setShippingMethod(
                        this.selectedShippingMethod.handle,
                        this.selectedShippingMethod.name
                    );
                }
                this.showSelect = false;
                this.$store.commit('updateShippingMethodOptions', null);
            },
        },
    };
</script>

<style lang="scss">
    #order-edit-shipping-method-wrapper {
        width: 100%;
    }
</style>
